<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\models\ChildProfile;
use app\models\Family;
use app\models\FamilyMember;
use app\models\MealEntry;
use app\models\MealPlanWeek;
use app\models\MealSlot;
use app\models\Recipe;
use Yii;
use yii\db\Query;

/**
 * Weekly meal planner: weeks (created on first access), slots, dishes,
 * templates, children's votes and proposals.
 */
class MealPlanService
{
    public const MAX_ENTRIES_PER_SLOT = 6;

    public function __construct(private readonly Family $family)
    {
    }

    /** Validates that $date is the first day of a week (family setting). */
    public function assertWeekStart(string $date): void
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        $startsOn = (int) ($this->family->getEffectiveSettings()['week_starts_on'] ?? 1);
        if ($d === false || $d->format('Y-m-d') !== $date || (int) $d->format('N') !== $startsOn) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'week_start must be the first day of a week.', 422, 'week_start');
        }
    }

    public function getOrCreateWeek(string $weekStart): MealPlanWeek
    {
        $this->assertWeekStart($weekStart);
        $week = MealPlanWeek::find()->forFamily($this->family->id)->andWhere(['week_start' => $weekStart, 'is_template' => false])->one();
        if ($week instanceof MealPlanWeek) {
            return $week;
        }
        $week = new MealPlanWeek(['week_start' => $weekStart, 'is_template' => false]);
        $week->family_id = $this->family->id;
        try {
            $week->save(false);
        } catch (\yii\db\IntegrityException) {
            // created concurrently
            $week = MealPlanWeek::find()->forFamily($this->family->id)->andWhere(['week_start' => $weekStart])->one();
        }
        /** @var MealPlanWeek $week */
        return $week;
    }

    /**
     * Full week for the planner, with votes seen by $voter.
     *
     * @param array{type: string, id: int} $voter
     * @return array<string, mixed>
     */
    public function serialize(MealPlanWeek $week, array $voter, bool $includeRejected = false): array
    {
        $mealTypes = (array) ($this->family->getEffectiveSettings()['meal_types'] ?? MealSlot::MEAL_TYPES);
        /** @var MealSlot[] $slots */
        $slots = MealSlot::find()->where(['week_id' => $week->id])
            ->with(['entries.recipe', 'entries.proposedBy'])->all();
        $byKey = [];
        foreach ($slots as $slot) {
            $byKey[$slot->day_index . ':' . $slot->meal_type] = $slot;
        }
        $votes = $this->votes(array_merge(...array_map(fn (MealSlot $s): array => array_map(fn (MealEntry $e): int => (int) $e->id, $s->entries), $slots ?: [new MealSlot()])), $voter);

        $start = new \DateTimeImmutable((string) $week->week_start, new \DateTimeZone('UTC'));
        $out = [];
        for ($day = 0; $day < 7; $day++) {
            foreach (MealSlot::MEAL_TYPES as $type) {
                if (!in_array($type, $mealTypes, true)) {
                    continue;
                }
                $slot = $byKey["$day:$type"] ?? null;
                $entries = [];
                foreach ($slot === null ? [] : $slot->entries as $entry) {
                    if ($entry->status === MealEntry::STATUS_REJECTED && !$includeRejected) {
                        continue;
                    }
                    $entries[] = $this->entryJson($entry, $votes[(int) $entry->id] ?? null);
                }
                $out[] = [
                    'id' => $slot?->id === null ? null : (int) $slot->id,
                    'day_index' => $day,
                    'date' => $start->modify("+$day days")->format('Y-m-d'),
                    'meal_type' => $type,
                    'servings' => (int) ($slot->servings ?? $this->defaultServings()),
                    'entries' => $entries,
                ];
            }
        }
        return [
            'id' => (int) $week->id,
            'week_start' => $week->week_start,
            'notes' => $week->notes,
            'meal_types' => array_values(array_intersect(MealSlot::MEAL_TYPES, $mealTypes)),
            'slots' => $out,
        ];
    }

    /**
     * Replaces the planned dishes of a slot (children's proposals are kept).
     *
     * @param array<string, mixed> $data {servings?, entries: [{id?, recipe_id?, free_text?}]}
     */
    public function putSlot(MealPlanWeek $week, int $day, string $mealType, array $data): MealSlot
    {
        $this->assertSlotKey($day, $mealType);
        $entries = $data['entries'] ?? [];
        if (!is_array($entries) || count($entries) > self::MAX_ENTRIES_PER_SLOT) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'entries must be a list (max ' . self::MAX_ENTRIES_PER_SLOT . ').', 422, 'entries');
        }
        $servings = $data['servings'] ?? null;
        if ($servings !== null && (!is_numeric($servings) || (int) $servings < 1 || (int) $servings > 30)) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'servings must be between 1 and 30.', 422, 'servings');
        }
        $clean = [];
        foreach (array_values($entries) as $i => $entry) {
            $clean[] = $this->cleanDish(is_array($entry) ? $entry : [], "entries.$i") + ['id' => isset($entry['id']) ? (int) $entry['id'] : null];
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $slot = $this->slot($week, $day, $mealType);
            if ($servings !== null) {
                $slot->updateAttributes(['servings' => (int) $servings]);
            }
            $planned = MealEntry::find()->where(['slot_id' => $slot->id, 'status' => MealEntry::STATUS_PLANNED])->indexBy('id')->all();
            $keep = [];
            foreach ($clean as $order => $dish) {
                $entry = $dish['id'] !== null ? ($planned[$dish['id']] ?? null) : null;
                $entry ??= new MealEntry(['slot_id' => $slot->id, 'status' => MealEntry::STATUS_PLANNED]);
                $entry->recipe_id = $dish['recipe_id'];
                $entry->free_text = $dish['free_text'];
                $entry->sort_order = $order;
                $entry->save(false);
                $keep[] = (int) $entry->id;
            }
            MealEntry::deleteAll(['and', ['slot_id' => $slot->id, 'status' => MealEntry::STATUS_PLANNED], ['not in', 'id', $keep ?: [0]]]);
            $week->updateAttributes(['updated_at' => time()]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return $slot;
    }

    /** Replaces the planned dishes of $target with those of $source (week or template). */
    public function copy(MealPlanWeek $source, MealPlanWeek $target): void
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $targetSlotIds = MealSlot::find()->where(['week_id' => $target->id])->select('id')->column();
            MealEntry::deleteAll(['slot_id' => $targetSlotIds ?: [0], 'status' => MealEntry::STATUS_PLANNED]);
            /** @var MealSlot $slot */
            foreach (MealSlot::find()->where(['week_id' => $source->id])->with('entries')->all() as $slot) {
                $copy = $this->slot($target, (int) $slot->day_index, $slot->meal_type);
                $copy->updateAttributes(['servings' => (int) $slot->servings]);
                foreach ($slot->entries as $entry) {
                    if ($entry->status !== MealEntry::STATUS_PLANNED) {
                        continue;
                    }
                    (new MealEntry([
                        'slot_id' => $copy->id,
                        'recipe_id' => $entry->recipe_id,
                        'free_text' => $entry->free_text,
                        'status' => MealEntry::STATUS_PLANNED,
                        'sort_order' => $entry->sort_order,
                    ]))->save(false);
                }
            }
            $target->updateAttributes(['updated_at' => time()]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public function saveAsTemplate(MealPlanWeek $week, string $name): MealPlanWeek
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Template name is required (max 60).', 422, 'name');
        }
        $template = new MealPlanWeek(['is_template' => true, 'template_name' => $name, 'week_start' => null]);
        $template->family_id = $this->family->id;
        $template->save(false);
        $this->copy($week, $template);
        return $template;
    }

    /**
     * @param array{type: string, id: int} $voter
     */
    public function vote(MealEntry $entry, array $voter, int $value): void
    {
        if (!in_array($value, [-1, 0, 1], true)) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'value must be 1, -1 or 0.', 422, 'value');
        }
        if ($entry->status === MealEntry::STATUS_REJECTED) {
            throw new ApiException(ErrorCode::INVALID_TRANSITION, 'Cannot vote a rejected dish.', 422);
        }
        $key = ['entry_id' => $entry->id, 'voter_type' => $voter['type'], 'voter_id' => $voter['id']];
        $db = Yii::$app->db;
        $db->createCommand()->delete('{{%meal_vote}}', $key)->execute();
        if ($value !== 0) {
            $db->createCommand()->insert('{{%meal_vote}}', $key + ['value' => $value])->execute();
        }
    }

    /**
     * @param array<string, mixed> $data {day, meal_type, recipe_id?|free_text?}
     */
    public function propose(MealPlanWeek $week, ChildProfile $child, array $data): MealEntry
    {
        $day = (int) ($data['day'] ?? -1);
        $mealType = (string) ($data['meal_type'] ?? '');
        $this->assertSlotKey($day, $mealType);
        $dish = $this->cleanDish($data, '');
        $slot = $this->slot($week, $day, $mealType);
        $pending = MealEntry::find()->where(['slot_id' => $slot->id, 'status' => MealEntry::STATUS_PROPOSED, 'proposed_by_child_id' => $child->id])->exists();
        if ($pending) {
            throw new ApiException(ErrorCode::CONFLICT, 'You already proposed a dish for this meal.', 409);
        }
        $entry = new MealEntry([
            'slot_id' => $slot->id,
            'recipe_id' => $dish['recipe_id'],
            'free_text' => $dish['free_text'],
            'status' => MealEntry::STATUS_PROPOSED,
            'proposed_by_child_id' => $child->id,
            'sort_order' => 100,
        ]);
        $entry->save(false);
        NotificationService::create()->notifyParents((int) $this->family->id, 'dish_proposed', [
            'name' => $child->name,
            'title' => $entry->getTitle(),
        ], '/menu/' . $week->week_start);
        return $entry;
    }

    public function review(MealEntry $entry, bool $accept): void
    {
        if ($entry->status !== MealEntry::STATUS_PROPOSED) {
            throw new ApiException(ErrorCode::INVALID_TRANSITION, "The dish is already {$entry->status}.", 422);
        }
        $entry->updateAttributes(['status' => $accept ? MealEntry::STATUS_PLANNED : MealEntry::STATUS_REJECTED]);
    }

    /** Finds an entry of this family (through slot → week), or 404. */
    public function findEntry(int $id): MealEntry
    {
        $entry = MealEntry::find()->alias('e')
            ->innerJoin(MealSlot::tableName() . ' s', 's.id = e.slot_id')
            ->innerJoin(MealPlanWeek::tableName() . ' w', 'w.id = s.week_id')
            ->where(['e.id' => $id, 'w.family_id' => $this->family->id])
            ->one();
        if (!$entry instanceof MealEntry) {
            throw new \yii\web\NotFoundHttpException("Object not found: $id");
        }
        return $entry;
    }

    /**
     * @param array{up: int, down: int, mine: int}|null $votes
     * @return array<string, mixed>
     */
    public function entryJson(MealEntry $entry, ?array $votes = null): array
    {
        $proposer = $entry->proposedBy;
        return [
            'id' => (int) $entry->id,
            'recipe_id' => $entry->recipe_id === null ? null : (int) $entry->recipe_id,
            'recipe' => $entry->recipe?->toSummary(),
            'free_text' => $entry->free_text,
            'title' => $entry->getTitle(),
            'status' => $entry->status,
            'proposed_by' => $proposer?->toProfile(),
            'votes' => $votes ?? ['up' => 0, 'down' => 0, 'mine' => 0],
        ];
    }

    /**
     * Voter identity of the caller (parents vote as user, children as child).
     *
     * @return array{type: string, id: int}
     */
    public static function voterOf(FamilyContext $context): array
    {
        $child = $context->getChild();
        if ($child !== null) {
            return ['type' => 'child', 'id' => (int) $child->id];
        }
        return ['type' => 'user', 'id' => (int) $context->getMember()->user_id];
    }

    /**
     * @param list<int> $entryIds
     * @param array{type: string, id: int} $voter
     * @return array<int, array{up: int, down: int, mine: int}>
     */
    private function votes(array $entryIds, array $voter): array
    {
        $entryIds = array_filter($entryIds);
        if ($entryIds === []) {
            return [];
        }
        $out = [];
        foreach ((new Query())->from('{{%meal_vote}}')->where(['entry_id' => $entryIds])->all() as $row) {
            $id = (int) $row['entry_id'];
            $out[$id] ??= ['up' => 0, 'down' => 0, 'mine' => 0];
            $out[$id][(int) $row['value'] > 0 ? 'up' : 'down']++;
            if ($row['voter_type'] === $voter['type'] && (int) $row['voter_id'] === $voter['id']) {
                $out[$id]['mine'] = (int) $row['value'];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $dish
     * @return array{recipe_id: int|null, free_text: string|null}
     */
    private function cleanDish(array $dish, string $field): array
    {
        $recipeId = isset($dish['recipe_id']) ? (int) $dish['recipe_id'] : null;
        $text = isset($dish['free_text']) ? trim((string) $dish['free_text']) : null;
        $text = $text === '' ? null : $text;
        $prefix = $field === '' ? '' : "$field: ";
        if ($recipeId === null && $text === null) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, $prefix . 'recipe_id or free_text required.', 422, $field ?: 'free_text');
        }
        if ($text !== null && mb_strlen($text) > 120) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, $prefix . 'free_text max 120 characters.', 422, $field ?: 'free_text');
        }
        if ($recipeId !== null && !Recipe::find()->forFamily($this->family->id)->andWhere(['id' => $recipeId])->exists()) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, $prefix . 'unknown recipe.', 422, $field ?: 'recipe_id');
        }
        return ['recipe_id' => $recipeId, 'free_text' => $recipeId === null ? $text : null];
    }

    private function assertSlotKey(int $day, string $mealType): void
    {
        if ($day < 0 || $day > 6) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'day must be 0–6.', 422, 'day');
        }
        if (!in_array($mealType, MealSlot::MEAL_TYPES, true)) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown meal type.', 422, 'meal_type');
        }
    }

    private function slot(MealPlanWeek $week, int $day, string $mealType): MealSlot
    {
        $slot = MealSlot::find()->where(['week_id' => $week->id, 'day_index' => $day, 'meal_type' => $mealType])->one();
        if ($slot instanceof MealSlot) {
            return $slot;
        }
        $slot = new MealSlot(['week_id' => $week->id, 'day_index' => $day, 'meal_type' => $mealType, 'servings' => $this->defaultServings()]);
        $slot->save(false);
        return $slot;
    }

    /** Parents + active children, at least 1. */
    private function defaultServings(): int
    {
        $parents = (int) FamilyMember::find()->forFamily($this->family->id)->count();
        $children = (int) ChildProfile::kids(ChildProfile::find()->forFamily($this->family->id))->andWhere(['archived_at' => null])->count();
        return max(1, min(30, $parents + $children));
    }
}
