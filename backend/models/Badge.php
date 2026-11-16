<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Global badge catalog; name is the i18n key `badges.<code>`.
 *
 * @property int $id
 * @property string $code
 * @property string $icon
 * @property array{type: string, threshold: int, time_of_day?: string} $rule
 * @property int $sort_order
 */
class Badge extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%badge}}';
    }
}
