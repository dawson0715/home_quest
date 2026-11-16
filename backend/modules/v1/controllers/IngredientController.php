<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\Ingredient;
use app\models\ShoppingCategory;
use app\services\ShoppingListService;
use Yii;
use yii\base\Model;

/**
 * Ingredient autocomplete (global catalog + family) and custom ingredients.
 */
class IngredientController extends ApiController
{
    /**
     * GET /ingredients?q= (prefix matches first, max 20)
     *
     * @return list<Ingredient>
     */
    public function actionIndex(): array
    {
        $familyId = $this->parentFamilyId();
        $q = trim((string) Yii::$app->request->get('q', ''));
        $query = Ingredient::visibleTo($familyId)->limit(20);
        if ($q !== '') {
            $query->andWhere(['like', 'name', $q])
                ->orderBy(new \yii\db\Expression('(name LIKE :prefix) DESC, name ASC', [':prefix' => addcslashes($q, '%_\\') . '%']));
        } else {
            $query->orderBy(['name' => SORT_ASC]);
        }
        return $query->all();
    }

    /** POST /ingredients {name, default_unit, shopping_category_id} → 201 */
    public function actionCreate(): Ingredient|Model
    {
        $familyId = $this->parentFamilyId();
        $body = Yii::$app->request;
        $ingredient = new Ingredient([
            'family_id' => $familyId,
            'name' => $body->getBodyParam('name'),
            'default_unit' => $body->getBodyParam('default_unit', 'pz'),
            'shopping_category_id' => $body->getBodyParam('shopping_category_id', ShoppingCategory::fallbackId()),
        ]);
        if (!$ingredient->validate()) {
            return $ingredient;
        }
        if (!ShoppingCategory::visibleTo($familyId)->andWhere(['id' => $ingredient->shopping_category_id])->exists()) {
            $ingredient->addError('shopping_category_id', 'Unknown category.');
            return $ingredient;
        }
        if (Ingredient::visibleTo($familyId)->andWhere(['name' => $ingredient->name])->exists()) {
            $ingredient->addError('name', 'This ingredient already exists.');
            return $ingredient;
        }
        $ingredient->save(false);
        Yii::$app->response->statusCode = 201;
        return $ingredient;
    }

    /**
     * GET /shopping-categories (in the family's department order)
     *
     * @return list<ShoppingCategory>
     */
    public function actionCategories(): array
    {
        $familyId = $this->parentFamilyId();
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $order = ShoppingListService::for($context->getFamily())->categoryOrder();
        $categories = ShoppingCategory::visibleTo($familyId)->indexBy('id')->all();
        return array_values(array_filter(array_map(fn (int $id): ?ShoppingCategory => $categories[$id] ?? null, array_keys($order))));
    }

    private function parentFamilyId(): int
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $context->requireParent();
        return $context->getId();
    }
}
