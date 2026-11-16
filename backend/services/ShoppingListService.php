<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\Clock;
use app\components\ErrorCode;
use app\models\Family;
use app\models\FavoriteItem;
use app\models\Ingredient;
use app\models\MealEntry;
use app\models\MealPlanWeek;
use app\models\MealSlot;
use app\models\RecipeIngredient;
use app\models\ShoppingCategory;
use app\models\ShoppingItem;
use app\models\ShoppingList;
use Yii;
use yii\db\Query;

/**
 * Shopping lists: items, check/uncheck, offline sync, pantry, favorites and
 * generation from the weekly menu. Every change bumps list.version (ETag).
 */
class ShoppingListService
{
    public function __construct(
        private readonly Family $family,
        private readonly Clock $clock,
    ) {
    }

    public static function for(Family $family): self
    {
        return new self($family, Yii::$app->get('clock'));
    }

    /**
     * @return list<ShoppingList>
     */
    public function lists(): array
    {
        $lists = ShoppingList::find()->forFamily($this->family->id)->orderBy(['is_default' => SORT_DESC, 'id' => SORT_ASC])->all();
        if ($lists !== []) {
            return $lists;
        }
        $default = new ShoppingList(['name' => 'Spesa', 'is_default' => true]);
        $default->family_id = $this->family->id;
        $default->save(false);
        return [$default];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ShoppingList $list): array
    {
        $order = $this->categoryOrder();
        $items = ShoppingItem::find()->where(['list_id' => $list->id])->all();
        usort($items, function (ShoppingItem $a, ShoppingItem $b) use ($order): int {
            return [($a->checked_at !== null), $order[(int) $a->category_id] ?? 999, $a->sort_order, $a->id]
                <=> [($b->checked_at !== null), $order[(int) $b->category_id] ?? 999, $b->sort_order, $b->id];
        });
        return [
            'id' => (int) $list->id,
            'name' => $list->name,
            'is_default' => (bool) $list->is_default,
            'version' => (int) $list->version,
            'linked_week_id' => $list->linked_week_id === null ? null : (int) $list->linked_week_id,
            'items' => array_map(fn (ShoppingItem $i): array => $i->toArray(), $items),
            'pantry' => array_map('intval', (new Query())->select('ingredient_id')->from('{{%pantry_exclusion}}')->where(['list_id' => $list->id])->column()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(ShoppingList $list): array
    {
        $counts = (new Query())->from(ShoppingItem::tableName())->where(['list_id' => $list->id])
            ->select(['total' => 'COUNT(*)', 'open' => 'SUM(checked_at IS NULL)'])->one();
        return [
            'id' => (int) $list->id,
            'name' => $list->name,
            'is_default' => (bool) $list->is_default,
            'version' => (int) $list->version,
            'items_count' => (int) ($counts['total'] ?? 0),
            'open_count' => (int) ($counts['open'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data {label?, ingredient_id?, quantity?, unit?, category_id?, client_uuid?}
     */
    public function addItem(ShoppingList $list, array $data): ShoppingItem
    {
        $uuid = isset($data['client_uuid']) ? (string) $data['client_uuid'] : null;
        if ($uuid !== null) {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                throw new ApiException(ErrorCode::VALIDATION_FAILED, 'client_uuid must be a UUID.', 422, 'client_uuid');
            }
            $existing = ShoppingItem::findOne(['client_uuid' => strtolower($uuid)]);
            if ($existing instanceof ShoppingItem) {
                if ((int) $existing->list_id !== (int) $list->id) {
                    throw new ApiException(ErrorCode::CONFLICT, 'client_uuid already used.', 409);
                }
                return $existing; // idempotent retry
            }
        }

        $ingredient = null;
        if (isset($data['ingredient_id'])) {
            $ingredient = Ingredient::visibleTo($this->family->id)->andWhere(['ingredient.id' => (int) $data['ingredient_id']])->one();
            if (!$ingredient instanceof Ingredient) {
                throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown ingredient.', 422, 'ingredient_id');
            }
        }
        $label = trim((string) ($data['label'] ?? $ingredient->name ?? ''));
        $favorite = $label === '' ? null : FavoriteItem::find()->forFamily($this->family->id)->andWhere(['label' => $label])->one();
        $categoryId = $data['category_id'] ?? $ingredient->shopping_category_id ?? $favorite->category_id ?? ShoppingCategory::fallbackId();
        if (!ShoppingCategory::visibleTo($this->family->id)->andWhere(['id' => (int) $categoryId])->exists()) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown category.', 422, 'category_id');
        }

        $item = new ShoppingItem([
            'list_id' => $list->id,
            'ingredient_id' => $ingredient->id ?? $favorite->ingredient_id ?? null,
            'label' => $label,
            'quantity' => $data['quantity'] ?? null,
            'unit' => $data['unit'] ?? null,
            'category_id' => (int) $categoryId,
            'source' => 'manual',
            'client_uuid' => $uuid === null ? null : strtolower($uuid),
            'sort_order' => 1 + (int) ShoppingItem::find()->where(['list_id' => $list->id])->max('sort_order'),
        ]);
        if (!$item->validate()) {
            $field = (string) array_key_first($item->getErrors());
            throw new ApiException(ErrorCode::VALIDATION_FAILED, (string) $item->getFirstError($field), 422, $field);
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            $item->save(false);
            $this->rememberFavorite($item);
            $this->bump($list);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return $item;
    }

    /**
     * @param array<string, mixed> $data {label?, quantity?, unit?, category_id?}
     */
    public function updateItem(ShoppingItem $item, array $data): ShoppingItem
    {
        foreach (['label', 'quantity', 'unit', 'category_id'] as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $item->$attribute = $data[$attribute];
            }
        }
        if (!$item->validate()) {
            $field = (string) array_key_first($item->getErrors());
            throw new ApiException(ErrorCode::VALIDATION_FAILED, (string) $item->getFirstError($field), 422, $field);
        }
        if (!ShoppingCategory::visibleTo($this->family->id)->andWhere(['id' => (int) $item->category_id])->exists()) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown category.', 422, 'category_id');
        }
        $item->save(false);
        $this->bump($this->listOf($item));
        return $item;
    }

    /**
     * @param array{type: string, id: int} $by
     */
    public function setChecked(ShoppingItem $item, bool $checked, array $by): ShoppingItem
    {
        if (($item->checked_at !== null) !== $checked) {
            $item->checked_at = $checked ? $this->clock->nowDb() : null;
            $item->checked_by_type = $checked ? $by['type'] : null;
            $item->checked_by_id = $checked ? $by['id'] : null;
            $item->save(false);
            $this->bump($this->listOf($item));
        }
        return $item;
    }

    public function deleteItem(ShoppingItem $item): void
    {
        $list = $this->listOf($item);
        $item->delete();
        $this->bump($list);
    }

    public function clearChecked(ShoppingList $list): int
    {
        $removed = ShoppingItem::deleteAll(['and', ['list_id' => $list->id], ['not', ['checked_at' => null]]]);
        if ($removed > 0) {
            $this->bump($list);
        }
        return $removed;
    }

    /**
     * Applies offline operations in order. Idempotent: `add` is keyed by client_uuid,
     * check/uncheck/delete on missing items are ignored. Children may only check/uncheck.
     *
     * @param list<array<string, mixed>> $ops (validated as arrays by the controller)
     * @param array{type: string, id: int} $by
     * @return array{applied: int, skipped: int}
     */
    public function sync(ShoppingList $list, array $ops, array $by, bool $childOnlyCheck): array
    {
        if (count($ops) > 200) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Too many operations (max 200).', 422, 'ops');
        }
        $applied = $skipped = 0;
        foreach ($ops as $op) {
            $type = (string) ($op['op'] ?? '');
            if ($childOnlyCheck && !in_array($type, ['check', 'uncheck'], true)) {
                $skipped++;
                continue;
            }
            try {
                if ($type === 'add') {
                    $this->addItem($list, ((array) ($op['data'] ?? [])) + ['client_uuid' => $op['client_uuid'] ?? null]);
                    $applied++;
                    continue;
                }
                $item = $this->resolveOpItem($list, $op);
                if ($item === null || !in_array($type, ['check', 'uncheck', 'delete'], true)) {
                    $skipped++;
                    continue;
                }
                match ($type) {
                    'check' => $this->setChecked($item, true, $by),
                    'uncheck' => $this->setChecked($item, false, $by),
                    'delete' => $this->deleteItem($item),
                };
                $applied++;
            } catch (ApiException) {
                $skipped++;
            }
        }
        return ['applied' => $applied, 'skipped' => $skipped];
    }

    public function setPantry(ShoppingList $list, int $ingredientId, bool $excluded): void
    {
        if (!Ingredient::visibleTo($this->family->id)->andWhere(['ingredient.id' => $ingredientId])->exists()) {
            throw new \yii\web\NotFoundHttpException("Object not found: $ingredientId");
        }
        $db = Yii::$app->db;
        $key = ['list_id' => $list->id, 'ingredient_id' => $ingredientId];
        $db->createCommand()->delete('{{%pantry_exclusion}}', $key)->execute();
        if ($excluded) {
            $db->createCommand()->insert('{{%pantry_exclusion}}', $key)->execute();
            ShoppingItem::deleteAll(['list_id' => $list->id, 'ingredient_id' => $ingredientId, 'source' => 'generated', 'checked_at' => null]);
        }
        $this->bump($list);
    }

    /**
     * Adds/updates the ingredients of the week's recipes (docs/02-architettura.md §6).
     * Manual and checked items are never touched; stale unchecked generated items
     * of the same week are removed.
     *
     * @return array{added: int, updated: int, removed: int}
     */
    public function generateFromWeek(ShoppingList $list, MealPlanWeek $week): array
    {
        $needs = $this->weekNeeds($week, $this->pantry($list));

        $existing = [];
        /** @var ShoppingItem $item */
        foreach (ShoppingItem::find()->where(['list_id' => $list->id, 'source' => 'generated', 'source_week_id' => $week->id])->all() as $item) {
            $existing[$item->ingredient_id . ':' . UnitConverter::baseUnit((string) $item->unit)] = $item;
        }

        $added = $updated = $removed = 0;
        $tx = Yii::$app->db->beginTransaction();
        try {
            $sort = (int) ShoppingItem::find()->where(['list_id' => $list->id])->max('sort_order');
            foreach ($needs as $key => $need) {
                [$quantity, $unit] = UnitConverter::humanize($need['quantity'], $need['base_unit']);
                $item = $existing[$key] ?? null;
                unset($existing[$key]);
                if ($item !== null) {
                    if ($item->checked_at === null && ((float) $item->quantity !== (float) $quantity || $item->unit !== $unit)) {
                        $item->quantity = $quantity === null ? null : (string) $quantity;
                        $item->unit = $unit;
                        $item->save(false);
                        $updated++;
                    }
                    continue;
                }
                (new ShoppingItem([
                    'list_id' => $list->id,
                    'ingredient_id' => $need['ingredient_id'],
                    'label' => $need['name'],
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'category_id' => $need['category_id'],
                    'source' => 'generated',
                    'source_week_id' => $week->id,
                    'sort_order' => ++$sort,
                ]))->save(false);
                $added++;
            }
            foreach ($existing as $stale) {
                if ($stale->checked_at === null) {
                    $stale->delete();
                    $removed++;
                }
            }
            $list->linked_week_id = $week->id;
            $this->bump($list);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return ['added' => $added, 'updated' => $updated, 'removed' => $removed];
    }

    /**
     * Summed needs of the week's planned recipes, scaled by servings.
     *
     * @param list<int> $excluded ingredient ids
     * @return array<string, array{ingredient_id: int, name: string, category_id: int, base_unit: string, quantity: float|null}>
     */
    public function weekNeeds(MealPlanWeek $week, array $excluded = []): array
    {
        $needs = [];
        $entries = (new Query())->from(MealEntry::tableName() . ' e')
            ->innerJoin(MealSlot::tableName() . ' s', 's.id = e.slot_id')
            ->where(['s.week_id' => $week->id, 'e.status' => MealEntry::STATUS_PLANNED])
            ->andWhere(['not', ['e.recipe_id' => null]])
            ->select(['recipe_id' => 'e.recipe_id', 'servings' => 's.servings'])
            ->all();
        foreach ($entries as $entry) {
            $recipe = \app\models\Recipe::find()->forFamily($this->family->id)->andWhere(['id' => (int) $entry['recipe_id']])->one();
            if ($recipe === null) {
                continue;
            }
            $factor = (int) $entry['servings'] / max(1, (int) $recipe->servings);
            /** @var RecipeIngredient $line */
            foreach ($recipe->recipeIngredients as $line) {
                if (in_array((int) $line->ingredient_id, $excluded, true)) {
                    continue;
                }
                $base = UnitConverter::baseUnit($line->unit);
                $key = $line->ingredient_id . ':' . $base;
                $needs[$key] ??= [
                    'ingredient_id' => (int) $line->ingredient_id,
                    'name' => $line->ingredient->name,
                    'category_id' => (int) $line->ingredient->shopping_category_id,
                    'base_unit' => $base,
                    'quantity' => $base === 'qb' ? null : 0.0,
                ];
                if ($needs[$key]['quantity'] !== null && $line->quantity !== null) {
                    $needs[$key]['quantity'] += UnitConverter::toBase((float) $line->quantity * $factor, $line->unit);
                }
            }
        }
        return $needs;
    }

    /**
     * Department order for this family (custom order or defaults).
     *
     * @return array<int, int> category id => position
     */
    public function categoryOrder(): array
    {
        $custom = (new Query())->select(['sort_order', 'category_id'])->from('{{%family_category_order}}')
            ->where(['family_id' => $this->family->id])->indexBy('category_id')->column();
        $order = [];
        foreach (ShoppingCategory::visibleTo($this->family->id)->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all() as $i => $c) {
            $order[(int) $c->id] = isset($custom[$c->id]) ? (int) $custom[$c->id] : 100 + $i;
        }
        asort($order);
        return $order;
    }

    /**
     * @param list<int> $ids full order of visible categories
     */
    public function setCategoryOrder(array $ids): void
    {
        $visible = array_map('intval', ShoppingCategory::visibleTo($this->family->id)->select('id')->column());
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (array_diff($ids, $visible) !== []) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown category.', 422, 'ids');
        }
        $db = Yii::$app->db;
        $db->createCommand()->delete('{{%family_category_order}}', ['family_id' => $this->family->id])->execute();
        $db->createCommand()->batchInsert('{{%family_category_order}}', ['family_id', 'category_id', 'sort_order'],
            array_map(fn (int $id, int $i): array => [$this->family->id, $id, $i], $ids, array_keys($ids)))->execute();
    }

    public function findList(int $id): ShoppingList
    {
        $list = ShoppingList::find()->forFamily($this->family->id)->andWhere(['id' => $id])->one();
        if (!$list instanceof ShoppingList) {
            throw new \yii\web\NotFoundHttpException("Object not found: $id");
        }
        return $list;
    }

    public function findItem(int $id): ShoppingItem
    {
        $item = ShoppingItem::find()->alias('i')
            ->innerJoin(ShoppingList::tableName() . ' l', 'l.id = i.list_id')
            ->where(['i.id' => $id, 'l.family_id' => $this->family->id])->one();
        if (!$item instanceof ShoppingItem) {
            throw new \yii\web\NotFoundHttpException("Object not found: $id");
        }
        return $item;
    }

    public function bump(ShoppingList $list): void
    {
        ShoppingList::updateAllCounters(['version' => 1], ['id' => $list->id]);
        $list->updateAttributes(['updated_at' => time(), 'linked_week_id' => $list->linked_week_id]);
        $list->version = (int) ShoppingList::find()->select('version')->where(['id' => $list->id])->scalar();
    }

    /**
     * @return list<int>
     */
    private function pantry(ShoppingList $list): array
    {
        return array_map('intval', (new Query())->select('ingredient_id')->from('{{%pantry_exclusion}}')->where(['list_id' => $list->id])->column());
    }

    /**
     * @param array<string, mixed> $op
     */
    private function resolveOpItem(ShoppingList $list, array $op): ?ShoppingItem
    {
        if (isset($op['item_id'])) {
            return ShoppingItem::findOne(['id' => (int) $op['item_id'], 'list_id' => $list->id]);
        }
        if (isset($op['client_uuid'])) {
            return ShoppingItem::findOne(['client_uuid' => strtolower((string) $op['client_uuid']), 'list_id' => $list->id]);
        }
        return null;
    }

    private function listOf(ShoppingItem $item): ShoppingList
    {
        return $this->findList((int) $item->list_id);
    }

    private function rememberFavorite(ShoppingItem $item): void
    {
        Yii::$app->db->createCommand(
            'INSERT INTO {{%favorite_item}} (family_id, label, ingredient_id, category_id, use_count) VALUES (:f, :l, :i, :c, 1)
             ON DUPLICATE KEY UPDATE use_count = use_count + 1, category_id = VALUES(category_id)',
            [':f' => $this->family->id, ':l' => $item->label, ':i' => $item->ingredient_id, ':c' => $item->category_id]
        )->execute();
    }
}
