<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Ingredient catalog: global (family_id NULL, seeded, Italian data) + family custom ones.
 *
 * @property int $id
 * @property int|null $family_id
 * @property string $name
 * @property string $default_unit
 * @property int $shopping_category_id
 *
 * @property-read ShoppingCategory $category
 */
class Ingredient extends ActiveRecord
{
    public const UNITS = ['g', 'kg', 'ml', 'l', 'pz', 'cucchiaio', 'cucchiaino', 'qb'];

    public static function tableName(): string
    {
        return '{{%ingredient}}';
    }

    /** Global + the family's own ingredients. */
    public static function visibleTo(int $familyId): ActiveQuery
    {
        return static::find()->where(['or', ['ingredient.family_id' => null], ['ingredient.family_id' => $familyId]]);
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            ['name', 'trim'],
            [['name', 'default_unit', 'shopping_category_id'], 'required'],
            ['name', 'string', 'max' => 80],
            ['default_unit', 'in', 'range' => self::UNITS],
            ['shopping_category_id', 'integer'],
        ];
    }

    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(ShoppingCategory::class, ['id' => 'shopping_category_id']);
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $i): int => (int) $i->id,
            'name',
            'default_unit',
            'shopping_category_id' => fn (self $i): int => (int) $i->shopping_category_id,
            'is_custom' => fn (self $i): bool => $i->family_id !== null,
        ];
    }
}
