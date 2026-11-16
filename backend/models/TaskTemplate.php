<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Global (not family-scoped) catalog of suggested tasks; title is an i18n key.
 *
 * @property int $id
 * @property string $title_key
 * @property string $icon
 * @property int $min_age
 * @property int $max_age
 * @property int $default_points
 * @property string $category
 * @property string $time_of_day
 * @property string $recurrence_type
 * @property int $sort_order
 */
class TaskTemplate extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%task_template}}';
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $t): int => (int) $t->id,
            'title_key',
            'icon',
            'min_age' => fn (self $t): int => (int) $t->min_age,
            'max_age' => fn (self $t): int => (int) $t->max_age,
            'default_points' => fn (self $t): int => (int) $t->default_points,
            'category',
            'time_of_day',
            'recurrence_type',
        ];
    }
}
