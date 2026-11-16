<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * A shared shopping list. `version` grows at every change (ETag for polling).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property bool|int $is_default
 * @property int|null $linked_week_id
 * @property int $version
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read ShoppingItem[] $items
 */
class ShoppingList extends FamilyScopedRecord
{
    public static function tableName(): string
    {
        return '{{%shopping_list}}';
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
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 60],
        ];
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(ShoppingItem::class, ['list_id' => 'id']);
    }

    public function etag(): string
    {
        return '"v' . (int) $this->version . '"';
    }
}
