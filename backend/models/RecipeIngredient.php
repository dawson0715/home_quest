<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $recipe_id
 * @property int $ingredient_id
 * @property string|null $quantity DECIMAL(10,2)
 * @property string $unit
 * @property string|null $note
 * @property int $sort_order
 *
 * @property-read Ingredient $ingredient
 */
class RecipeIngredient extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%recipe_ingredient}}';
    }

    public function getIngredient(): ActiveQuery
    {
        return $this->hasOne(Ingredient::class, ['id' => 'ingredient_id']);
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $i): int => (int) $i->id,
            'ingredient_id' => fn (self $i): int => (int) $i->ingredient_id,
            'name' => fn (self $i): string => $i->ingredient->name,
            'quantity' => fn (self $i): ?float => $i->quantity === null ? null : (float) $i->quantity,
            'unit',
            'note',
        ];
    }
}
