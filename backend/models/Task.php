<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * A one-off task or a recurring routine. Write it through TaskService (assignees,
 * checklist and today's occurrences are kept in sync there).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property string|null $description
 * @property string $icon
 * @property int $points
 * @property int $xp
 * @property bool|int $requires_approval
 * @property string $recurrence_type
 * @property int|null $recurrence_days bitmask Mon=1 … Sun=64
 * @property int|null $interval_days
 * @property string $time_of_day
 * @property string|null $due_date
 * @property string $start_date
 * @property string|null $end_date
 * @property int|null $linked_meal_slot_id
 * @property bool|int $is_active
 * @property int|null $created_by
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read TaskChecklistItem[] $checklistItems
 * @property-read ChildProfile[] $assignees
 */
class Task extends FamilyScopedRecord
{
    public const RECURRENCE_NONE = 'none';
    public const RECURRENCE_DAILY = 'daily';
    public const RECURRENCE_WEEKLY = 'weekly';
    public const RECURRENCE_INTERVAL = 'interval';
    public const RECURRENCES = [self::RECURRENCE_NONE, self::RECURRENCE_DAILY, self::RECURRENCE_WEEKLY, self::RECURRENCE_INTERVAL];

    public const TIMES_OF_DAY = ['morning', 'afternoon', 'evening', 'any'];

    public static function tableName(): string
    {
        return '{{%task}}';
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
            [['title', 'icon', 'points', 'recurrence_type', 'start_date'], 'required'],
            [['title', 'description', 'icon'], 'trim'],
            ['title', 'string', 'max' => 120],
            ['description', 'string', 'max' => 2000],
            ['description', 'default', 'value' => null],
            ['icon', 'string', 'max' => 40],
            ['points', 'integer', 'min' => 0, 'max' => 1000],
            ['xp', 'integer', 'min' => 0, 'max' => 1000],
            ['xp', 'default', 'value' => fn (self $t): int => (int) $t->points],
            ['requires_approval', 'default', 'value' => true],
            ['requires_approval', 'boolean'],
            ['requires_approval', 'filter', 'filter' => 'boolval'],
            ['recurrence_type', 'in', 'range' => self::RECURRENCES],
            ['time_of_day', 'in', 'range' => self::TIMES_OF_DAY],
            ['time_of_day', 'default', 'value' => 'any'],
            [['start_date', 'end_date', 'due_date'], 'date', 'format' => 'php:Y-m-d'],
            [['end_date', 'due_date', 'recurrence_days', 'interval_days'], 'default', 'value' => null],
            ['recurrence_days', 'integer', 'min' => 1, 'max' => 127],
            ['recurrence_days', 'required', 'when' => fn (self $t): bool => $t->recurrence_type === self::RECURRENCE_WEEKLY],
            ['interval_days', 'integer', 'min' => 1, 'max' => 365],
            ['interval_days', 'required', 'when' => fn (self $t): bool => $t->recurrence_type === self::RECURRENCE_INTERVAL],
            ['end_date', 'compare', 'compareAttribute' => 'start_date', 'operator' => '>=', 'type' => 'string'],
            ['due_date', 'compare', 'compareAttribute' => 'start_date', 'operator' => '>=', 'type' => 'string'],
            ['linked_meal_slot_id', 'integer'],
            ['is_active', 'default', 'value' => true],
            ['is_active', 'boolean'],
            ['is_active', 'filter', 'filter' => 'boolval'],
        ];
    }

    public function getChecklistItems(): ActiveQuery
    {
        return $this->hasMany(TaskChecklistItem::class, ['task_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getAssignees(): ActiveQuery
    {
        return $this->hasMany(ChildProfile::class, ['id' => 'child_id'])->viaTable('{{%task_assignee}}', ['task_id' => 'id']);
    }

    /**
     * @return list<int>
     */
    public function getAssigneeIds(): array
    {
        return array_map('intval', (new \yii\db\Query())
            ->select('child_id')->from('{{%task_assignee}}')->where(['task_id' => $this->id])->orderBy('child_id')->column());
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id',
            'title',
            'description',
            'icon',
            'points' => fn (self $t): int => (int) $t->points,
            'xp' => fn (self $t): int => (int) $t->xp,
            'requires_approval' => fn (self $t): bool => (bool) $t->requires_approval,
            'recurrence_type',
            'recurrence_days' => fn (self $t): ?int => $t->recurrence_days === null ? null : (int) $t->recurrence_days,
            'interval_days' => fn (self $t): ?int => $t->interval_days === null ? null : (int) $t->interval_days,
            'time_of_day',
            'due_date',
            'start_date',
            'end_date',
            'is_active' => fn (self $t): bool => (bool) $t->is_active,
            'linked_meal_slot_id' => fn (self $t): ?int => $t->linked_meal_slot_id === null ? null : (int) $t->linked_meal_slot_id,
            'assignee_ids' => fn (self $t): array => $t->getAssigneeIds(),
            'checklist' => fn (self $t): array => array_map(
                fn (TaskChecklistItem $i): array => $i->toArray(),
                $t->checklistItems
            ),
        ];
    }
}
