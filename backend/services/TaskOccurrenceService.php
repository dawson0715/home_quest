<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\Clock;
use app\components\ErrorCode;
use app\models\ChildProfile;
use app\models\Task;
use app\models\TaskOccurrence;
use Yii;

/**
 * State machine of task occurrences (docs/03-database.md, task_occurrence).
 * Every transition runs in a DB transaction with the occurrence row locked,
 * so double submits/approvals never award points twice.
 */
class TaskOccurrenceService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PointsService $points,
        private readonly OccurrenceGenerator $generator,
        private readonly BadgeService $badges,
    ) {
    }

    public static function create(): self
    {
        return new self(Yii::$app->get('clock'), new PointsService(), new OccurrenceGenerator(), new BadgeService());
    }

    /**
     * Occurrences shown on the child's "today" page (generated on demand).
     *
     * @return list<TaskOccurrence>
     */
    public function today(ChildProfile $child): array
    {
        $today = $this->clock->today();
        $this->generator->generateForDate($today, $child->family_id);

        return TaskOccurrence::find()
            ->alias('o')
            ->forFamily($child->family_id)
            ->innerJoinWith('task t')
            ->with(['task.checklistItems'])
            ->andWhere(['o.child_id' => $child->id])
            ->andWhere(['not', ['o.status' => TaskOccurrence::STATUS_TAKEN]])
            ->andWhere(['or',
                ['o.due_date' => $today],
                ['and',
                    ['t.recurrence_type' => Task::RECURRENCE_NONE],
                    ['or',
                        ['and', ['>', 'o.due_date', $today], ['not', ['o.status' => TaskOccurrence::STATUS_MISSED]]],
                        ['o.status' => [TaskOccurrence::STATUS_PENDING, TaskOccurrence::STATUS_SUBMITTED, TaskOccurrence::STATUS_REJECTED]],
                    ],
                ],
            ])
            ->orderBy(['t.title' => SORT_ASC, 'o.id' => SORT_ASC])
            ->all();
    }

    public function setChecklistItem(TaskOccurrence $occurrence, int $itemId, bool $done): TaskOccurrence
    {
        return $this->transition($occurrence, function (TaskOccurrence $o) use ($itemId, $done): void {
            $this->assertOpen($o);
            $ids = array_column($o->getChecklist(), 'id');
            if (!in_array($itemId, $ids, true)) {
                throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown checklist item.', 422, 'item_id');
            }
            $state = $o->checklist_state ?? [];
            $state[(string) $itemId] = $done;
            $o->checklist_state = $state;
            $o->save(false, ['checklist_state', 'updated_at']);
        });
    }

    /**
     * Child marks the task done: submitted, or approved straight away (points)
     * when the task does not require approval.
     */
    public function submit(TaskOccurrence $occurrence): TransitionResult
    {
        $result = new TransitionResult();
        $this->transition($occurrence, function (TaskOccurrence $o) use ($result): void {
            $this->assertOpen($o);
            if (!$o->isChecklistComplete()) {
                throw new ApiException(ErrorCode::CHECKLIST_INCOMPLETE, 'Complete the checklist first.', 422);
            }
            if ((bool) $o->task->is_open) {
                $this->claimOpenTask($o);
            }
            $o->submitted_at = $this->clock->nowDb();
            if ((bool) $o->task->requires_approval) {
                $o->status = TaskOccurrence::STATUS_SUBMITTED;
                $o->save(false);
                $result->notifyParents = true;
            } else {
                $this->approveLocked($o, null, null, $result);
            }
        });
        $result->occurrence = $occurrence;
        if ($result->notifyParents) {
            NotificationService::create()->notifyParents((int) $occurrence->family_id, 'task_submitted', [
                'name' => $occurrence->child->name,
                'title' => $occurrence->task->title,
            ], '/approvals');
        }
        return $result;
    }

    public function approve(TaskOccurrence $occurrence, int $parentUserId, ?string $note = null): TransitionResult
    {
        $result = new TransitionResult();
        $this->transition($occurrence, function (TaskOccurrence $o) use ($parentUserId, $note, $result): void {
            if ($o->status !== TaskOccurrence::STATUS_SUBMITTED) {
                throw new ApiException(ErrorCode::INVALID_TRANSITION, "Cannot approve a {$o->status} occurrence.", 422);
            }
            $this->approveLocked($o, $parentUserId, $note, $result);
        });
        $result->occurrence = $occurrence;
        return $result;
    }

    public function reject(TaskOccurrence $occurrence, int $parentUserId, ?string $note = null): TaskOccurrence
    {
        return $this->transition($occurrence, function (TaskOccurrence $o) use ($parentUserId, $note): void {
            if ($o->status !== TaskOccurrence::STATUS_SUBMITTED) {
                throw new ApiException(ErrorCode::INVALID_TRANSITION, "Cannot reject a {$o->status} occurrence.", 422);
            }
            $o->status = TaskOccurrence::STATUS_REJECTED;
            $o->reviewed_at = $this->clock->nowDb();
            $o->reviewed_by = $parentUserId;
            $o->review_note = $note;
            $o->save(false);
            if ((bool) $o->task->is_open) {
                // free again for everyone
                TaskOccurrence::updateAll(
                    ['status' => TaskOccurrence::STATUS_PENDING, 'updated_at' => time()],
                    ['task_id' => $o->task_id, 'due_date' => $o->due_date, 'status' => TaskOccurrence::STATUS_TAKEN]
                );
            }
        });
    }

    /**
     * Open task: the first player to complete it wins; the others' occurrences of
     * the same day become `taken`. Siblings are locked so two players cannot win.
     */
    private function claimOpenTask(TaskOccurrence $o): void
    {
        $siblings = TaskOccurrence::findBySql(
            'SELECT * FROM ' . TaskOccurrence::tableName() . ' WHERE task_id = :t AND due_date = :d AND id <> :id FOR UPDATE',
            [':t' => $o->task_id, ':d' => $o->due_date, ':id' => $o->id]
        )->all();
        foreach ($siblings as $sibling) {
            if (in_array($sibling->status, [TaskOccurrence::STATUS_SUBMITTED, TaskOccurrence::STATUS_APPROVED], true)) {
                throw new ApiException(ErrorCode::TASK_TAKEN, 'Someone else already did this task.', 409);
            }
        }
        TaskOccurrence::updateAll(
            ['status' => TaskOccurrence::STATUS_TAKEN, 'updated_at' => time()],
            ['task_id' => $o->task_id, 'due_date' => $o->due_date, 'status' => [TaskOccurrence::STATUS_PENDING, TaskOccurrence::STATUS_REJECTED]]
        );
    }

    private function approveLocked(TaskOccurrence $o, ?int $parentUserId, ?string $note, TransitionResult $result): void
    {
        $task = $o->task;
        $points = $this->points->award(
            $o->child,
            (int) $task->points,
            (int) $task->xp,
            'task',
            'task_occurrence',
            (int) $o->id,
            null,
            $parentUserId,
        );
        $o->status = TaskOccurrence::STATUS_APPROVED;
        $o->points_awarded = (int) $task->points;
        $o->reviewed_at = $this->clock->nowDb();
        $o->reviewed_by = $parentUserId;
        $o->review_note = $note;
        $o->save(false);

        $result->pointsAwarded = (int) $task->points;
        $result->levelUp = $points->levelUp;
        $result->balance = $points->balance;
        $result->newBadges = $this->badges->evaluate($o->child);
    }

    /** Only today's (or open one-off) pending/rejected occurrences can change. */
    private function assertOpen(TaskOccurrence $o): void
    {
        if (!in_array($o->status, [TaskOccurrence::STATUS_PENDING, TaskOccurrence::STATUS_REJECTED], true)) {
            throw new ApiException(ErrorCode::INVALID_TRANSITION, "The task is already {$o->status}.", 422);
        }
        $today = $this->clock->today();
        $isOneOff = $o->task->recurrence_type === Task::RECURRENCE_NONE;
        $open = $isOneOff ? ($o->task->due_date === null || $today <= $o->task->due_date) : $o->due_date === $today;
        if (!$open) {
            throw new ApiException(ErrorCode::INVALID_TRANSITION, 'This task is not for today.', 422);
        }
    }

    /**
     * Runs $change on the freshly locked row and copies the result into $occurrence.
     *
     * @param callable(TaskOccurrence): void $change
     */
    private function transition(TaskOccurrence $occurrence, callable $change): TaskOccurrence
    {
        $db = TaskOccurrence::getDb();
        $tx = $db->beginTransaction();
        try {
            $locked = TaskOccurrence::findBySql(
                'SELECT * FROM ' . TaskOccurrence::tableName() . ' WHERE id = :id FOR UPDATE',
                [':id' => $occurrence->id]
            )->one();
            if (!$locked instanceof TaskOccurrence) {
                throw new \RuntimeException('Occurrence vanished.');
            }
            $change($locked);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $occurrence->setAttributes($locked->getAttributes(), false);
        $occurrence->setOldAttributes($locked->getOldAttributes());
        return $occurrence;
    }
}
