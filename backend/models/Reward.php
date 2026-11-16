<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;

/**
 * A reward of the family shop.
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property string $icon
 * @property int $cost
 * @property int|null $limit_per_period
 * @property string|null $limit_period day|week|month
 * @property int|null $stock
 * @property bool|int $is_active
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 */
class Reward extends FamilyScopedRecord
{
    public const PERIODS = ['day', 'week', 'month'];

    public static function tableName(): string
    {
        return '{{%reward}}';
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
            [['title', 'icon', 'cost'], 'required'],
            [['title', 'icon'], 'trim'],
            ['title', 'string', 'max' => 120],
            ['icon', 'string', 'max' => 40],
            ['cost', 'integer', 'min' => 1, 'max' => 10000],
            [['limit_per_period', 'limit_period', 'stock'], 'default', 'value' => null],
            ['limit_per_period', 'integer', 'min' => 1, 'max' => 100],
            ['limit_period', 'in', 'range' => self::PERIODS],
            ['limit_period', 'required', 'when' => fn (self $r): bool => $r->limit_per_period !== null],
            ['limit_per_period', 'required', 'when' => fn (self $r): bool => $r->limit_period !== null],
            ['stock', 'integer', 'min' => 0, 'max' => 10000],
            ['is_active', 'default', 'value' => true],
            ['is_active', 'boolean'],
            ['is_active', 'filter', 'filter' => 'boolval'],
            ['sort_order', 'integer'],
            ['sort_order', 'default', 'value' => 0],
        ];
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $r): int => (int) $r->id,
            'title',
            'icon',
            'cost' => fn (self $r): int => (int) $r->cost,
            'limit_per_period' => fn (self $r): ?int => $r->limit_per_period === null ? null : (int) $r->limit_per_period,
            'limit_period',
            'stock' => fn (self $r): ?int => $r->stock === null ? null : (int) $r->stock,
            'is_active' => fn (self $r): bool => (bool) $r->is_active,
            'sort_order' => fn (self $r): int => (int) $r->sort_order,
        ];
    }
}
