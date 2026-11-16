<?php

declare(strict_types=1);

namespace app\services;

use app\models\Task;
use app\models\TaskOccurrence;
use Yii;

/**
 * Creates the task occurrences of a day. Idempotent (INSERT IGNORE on the unique
 * task_id + child_id + due_date): safe to run from cron and on demand.
 */
class OccurrenceGenerator
{
    /**
     * @return int number of occurrences created
     */
    public function generateForDate(string $date, ?int $familyId = null, ?int $taskId = null): int
    {
        $query = Task::find()
            ->where(['is_active' => true])
            ->andWhere(['<=', 'start_date', $date])
            ->andWhere(['or', ['end_date' => null], ['>=', 'end_date', $date]])
            ->with(['assignees']);
        if ($familyId !== null) {
            $query->forFamily($familyId);
        }
        if ($taskId !== null) {
            $query->andWhere(['id' => $taskId]);
        }

        $now = time();
        $rows = [];
        /** @var array<int, list<\app\models\ChildProfile>> $everyone */
        $everyone = [];
        foreach ($query->each(200) as $task) {
            /** @var Task $task */
            if (!RecurrenceRule::isDue($task, $date)) {
                continue;
            }
            $occurrenceDate = RecurrenceRule::occurrenceDate($task, $date);
            $players = $task->assignees;
            if ($players === [] && (bool) $task->is_open) {
                $players = $everyone[$task->family_id] ??= \app\models\ChildProfile::find()->forFamily($task->family_id)->andWhere(['archived_at' => null])->all();
            }
            foreach ($players as $child) {
                if ($child->archived_at !== null) {
                    continue;
                }
                $rows[] = [$task->family_id, $task->id, $child->id, $occurrenceDate, TaskOccurrence::STATUS_PENDING, $now, $now];
            }
        }
        if ($rows === []) {
            return 0;
        }

        $db = Yii::$app->db;
        $sql = $db->createCommand()->batchInsert(
            TaskOccurrence::tableName(),
            ['family_id', 'task_id', 'child_id', 'due_date', 'status', 'created_at', 'updated_at'],
            $rows
        )->getRawSql();

        return $db->createCommand('INSERT IGNORE' . substr($sql, strlen('INSERT')))->execute();
    }

    /**
     * Removes today's/future pending occurrences that the task no longer schedules
     * (task edited, deactivated, assignee removed). History is never touched.
     */
    public function pruneStale(Task $task, string $today): int
    {
        $assigneeIds = $task->getAssigneeIds();
        $removed = 0;
        $pending = TaskOccurrence::find()
            ->forFamily($task->family_id)
            ->andWhere(['task_id' => $task->id, 'status' => TaskOccurrence::STATUS_PENDING])
            ->andWhere(['>=', 'due_date', $task->recurrence_type === Task::RECURRENCE_NONE ? '0000-01-01' : $today])
            ->all();
        foreach ($pending as $occurrence) {
            $stillDue = ($assigneeIds === [] && (bool) $task->is_open || in_array((int) $occurrence->child_id, $assigneeIds, true))
                && RecurrenceRule::isDue($task, $task->recurrence_type === Task::RECURRENCE_NONE ? $today : $occurrence->due_date)
                && RecurrenceRule::occurrenceDate($task, $occurrence->due_date) === $occurrence->due_date;
            if (!$stillDue) {
                $occurrence->delete();
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * End of day: pending occurrences of past days become missed. One-off tasks
     * only once their deadline has passed.
     *
     * @return int occurrences marked missed
     */
    public function markMissed(string $today): int
    {
        return TaskOccurrence::updateAll(
            ['status' => TaskOccurrence::STATUS_MISSED, 'updated_at' => time()],
            ['and',
                ['status' => [TaskOccurrence::STATUS_PENDING, TaskOccurrence::STATUS_REJECTED]],
                ['<', 'due_date', $today],
                ['or',
                    ['not in', 'task_id', Task::find()->select('id')->where(['recurrence_type' => Task::RECURRENCE_NONE])],
                    ['in', 'task_id', Task::find()->select('id')->where(['recurrence_type' => Task::RECURRENCE_NONE])->andWhere(['not', ['due_date' => null]])],
                ],
            ]
        );
    }
}
