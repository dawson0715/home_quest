<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * A task instance for one child on one day. State changes go through TaskOccurrenceService.
 *
 * pending → submitted → approved | rejected; pending → approved when no approval is
 * required; rejected → submitted (retry); pending → missed at the end of the day.
 *
 * @property int $id
 * @property int $family_id
 * @property int $task_id
 * @property int $child_id
 * @property string $due_date
 * @property string $status
 * @property array<int|string, bool>|null $checklist_state item id => done
 * @property string|null $submitted_at
 * @property string|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property int|null $points_awarded
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Task $task
 * @property-read ChildProfile $child
 */
class TaskOccurrence extends FamilyScopedRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_MISSED = 'missed';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_SUBMITTED, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_MISSED];

    public static function tableName(): string
    {
        return '{{%task_occurrence}}';
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
        return [];
    }

    public function getTask(): ActiveQuery
    {
        return $this->hasOne(Task::class, ['id' => 'task_id']);
    }

    public function getChild(): ActiveQuery
    {
        return $this->hasOne(ChildProfile::class, ['id' => 'child_id']);
    }

    /**
     * @return list<array{id: int, label: string, icon: string|null, done: bool}>
     */
    public function getChecklist(): array
    {
        $state = $this->checklist_state ?? [];
        return array_map(fn (TaskChecklistItem $i): array => [
            'id' => (int) $i->id,
            'label' => $i->label,
            'icon' => $i->icon,
            'done' => (bool) ($state[(string) $i->id] ?? false),
        ], $this->task->checklistItems);
    }

    public function isChecklistComplete(): bool
    {
        foreach ($this->getChecklist() as $item) {
            if (!$item['done']) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $o): int => (int) $o->id,
            'task_id' => fn (self $o): int => (int) $o->task_id,
            'child_id' => fn (self $o): int => (int) $o->child_id,
            'due_date',
            'status',
            'title' => fn (self $o): string => $o->task->title,
            'icon' => fn (self $o): string => $o->task->icon,
            'points' => fn (self $o): int => (int) $o->task->points,
            'time_of_day' => fn (self $o): string => $o->task->time_of_day,
            'requires_approval' => fn (self $o): bool => (bool) $o->task->requires_approval,
            'checklist' => fn (self $o): array => $o->getChecklist(),
            'submitted_at',
            'reviewed_at',
            'review_note',
            'points_awarded' => fn (self $o): ?int => $o->points_awarded === null ? null : (int) $o->points_awarded,
        ];
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function extraFields(): array
    {
        return [
            'child' => fn (self $o): array => $o->child->toProfile(),
        ];
    }
}
