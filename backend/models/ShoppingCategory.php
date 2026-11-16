<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Store department. Global ones (family_id NULL) have an i18n `code`
 * (`shopping_categories.<code>`); family ones a free `name`.
 *
 * @property int $id
 * @property int|null $family_id
 * @property string|null $code
 * @property string|null $name
 * @property string $icon
 * @property int $sort_order
 */
class ShoppingCategory extends ActiveRecord
{
    public const FALLBACK_CODE = 'altro';

    public static function tableName(): string
    {
        return '{{%shopping_category}}';
    }

    /** Global + the family's own categories. */
    public static function visibleTo(int $familyId): ActiveQuery
    {
        return static::find()->where(['or', ['family_id' => null], ['family_id' => $familyId]]);
    }

    public static function fallbackId(): int
    {
        return (int) static::find()->select('id')->where(['family_id' => null, 'code' => self::FALLBACK_CODE])->scalar();
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $c): int => (int) $c->id,
            'code',
            'name',
            'icon',
            'sort_order' => fn (self $c): int => (int) $c->sort_order,
        ];
    }
}
