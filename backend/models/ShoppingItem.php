<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Item of a shopping list (reached through its family-scoped list).
 *
 * @property int $id
 * @property int $list_id
 * @property int|null $ingredient_id
 * @property string $label
 * @property string|null $quantity DECIMAL
 * @property string|null $unit
 * @property int $category_id
 * @property string $source manual|generated
 * @property int|null $source_week_id
 * @property string|null $checked_at
 * @property string|null $checked_by_type user|child
 * @property int|null $checked_by_id
 * @property string|null $client_uuid
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 */
class ShoppingItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%shopping_item}}';
    }

    public function getList(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ShoppingList::class, ['id' => 'list_id']);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [['label', 'unit'], 'trim'],
            ['label', 'required'],
            ['label', 'string', 'max' => 120],
            ['quantity', 'number', 'min' => 0.01, 'max' => 99999],
            ['quantity', 'default', 'value' => null],
            ['unit', 'string', 'max' => 12],
            ['unit', 'default', 'value' => null],
            ['category_id', 'integer'],
        ];
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $i): int => (int) $i->id,
            'ingredient_id' => fn (self $i): ?int => $i->ingredient_id === null ? null : (int) $i->ingredient_id,
            'label',
            'quantity' => fn (self $i): ?float => $i->quantity === null ? null : (float) $i->quantity,
            'unit',
            'category_id' => fn (self $i): int => (int) $i->category_id,
            'source',
            'checked' => fn (self $i): bool => $i->checked_at !== null,
            'checked_at',
            'client_uuid',
        ];
    }
}
