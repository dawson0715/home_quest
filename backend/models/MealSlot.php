<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * One meal of one day. Only reached through its (family-scoped) week.
 *
 * @property int $id
 * @property int $week_id
 * @property int $day_index 0–6 from week_start
 * @property string $meal_type
 * @property int $servings
 *
 * @property-read MealPlanWeek $week
 * @property-read MealEntry[] $entries
 */
class MealSlot extends ActiveRecord
{
    public const MEAL_TYPES = ['breakfast', 'lunch', 'snack', 'dinner'];

    public static function tableName(): string
    {
        return '{{%meal_slot}}';
    }

    public function getWeek(): ActiveQuery
    {
        return $this->hasOne(MealPlanWeek::class, ['id' => 'week_id']);
    }

    public function getEntries(): ActiveQuery
    {
        return $this->hasMany(MealEntry::class, ['slot_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }
}
