<?php

declare(strict_types=1);

namespace app\models;

/**
 * Frequently bought items of the family (one-tap add).
 *
 * @property int $id
 * @property int $family_id
 * @property string $label
 * @property int|null $ingredient_id
 * @property int $category_id
 * @property int $use_count
 */
class FavoriteItem extends FamilyScopedRecord
{
    public static function tableName(): string
    {
        return '{{%favorite_item}}';
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $f): int => (int) $f->id,
            'label',
            'ingredient_id' => fn (self $f): ?int => $f->ingredient_id === null ? null : (int) $f->ingredient_id,
            'category_id' => fn (self $f): int => (int) $f->category_id,
            'use_count' => fn (self $f): int => (int) $f->use_count,
        ];
    }
}
