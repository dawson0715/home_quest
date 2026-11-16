<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * A week of the meal planner, or a reusable template (is_template, week_start NULL).
 *
 * @property int $id
 * @property int $family_id
 * @property string|null $week_start
 * @property string|null $notes
 * @property bool|int $is_template
 * @property string|null $template_name
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read MealSlot[] $slots
 */
class MealPlanWeek extends FamilyScopedRecord
{
    public static function tableName(): string
    {
        return '{{%meal_plan_week}}';
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
            [['notes', 'template_name'], 'trim'],
            ['notes', 'string', 'max' => 2000],
            ['template_name', 'string', 'max' => 60],
        ];
    }

    public function getSlots(): ActiveQuery
    {
        return $this->hasMany(MealSlot::class, ['week_id' => 'id']);
    }
}
