<?php

declare(strict_types=1);

namespace app\services;

use app\components\Clock;
use app\forms\TaskForm;
use app\models\Task;
use app\models\TaskChecklistItem;
use app\models\TaskOccurrence;
use Yii;

/**
 * Creates/updates/deletes tasks keeping assignees, checklist and today's occurrences in sync.
 */
class TaskService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly OccurrenceGenerator $generator,
    ) {
    }

    public static function create(): self
    {
        return new self(Yii::$app->get('clock'), new OccurrenceGenerator());
    }

    public function newForm(?Task $task = null): TaskForm
    {
        return new TaskForm($task ?? new Task(), $this->clock->today());
    }

    /** Persists a validated form; returns false (errors on the form) if invalid. */
    public function save(TaskForm $form, ?int $userId): bool
    {
        if (!$form->validate()) {
            return false;
        }
        $task = $form->task;
        $tx = Task::getDb()->beginTransaction();
        try {
            if ($task->isNewRecord) {
                $task->created_by = $userId;
            }
            $task->save(false);
            if ($form->assigneeIds !== null) {
                $this->syncAssignees($task, $form->assigneeIds);
            }
            if ($form->checklist !== null) {
                $this->syncChecklist($task, $form->checklist);
            }
            $today = $this->clock->today();
            $this->generator->pruneStale($task, $today);
            $this->generator->generateForDate($today, $task->family_id, $task->id);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $task->refresh();
        return true;
    }

    /**
     * Hard delete when the task has no history, otherwise deactivate it (history kept).
     *
     * @return bool true if deleted, false if deactivated
     */
    public function delete(Task $task): bool
    {
        $hasHistory = TaskOccurrence::find()->forFamily($task->family_id)
            ->andWhere(['task_id' => $task->id])
            ->andWhere(['not', ['status' => TaskOccurrence::STATUS_PENDING]])
            ->exists();
        if (!$hasHistory) {
            $task->delete();
            return true;
        }
        $task->is_active = false;
        $task->save(false, ['is_active', 'updated_at']);
        $this->generator->pruneStale($task, $this->clock->today());
        return false;
    }

    /**
     * @param list<int> $childIds
     */
    private function syncAssignees(Task $task, array $childIds): void
    {
        $db = Task::getDb();
        $db->createCommand()->delete('{{%task_assignee}}', ['task_id' => $task->id])->execute();
        $db->createCommand()->batchInsert(
            '{{%task_assignee}}',
            ['task_id', 'child_id'],
            array_map(fn (int $id): array => [$task->id, $id], $childIds)
        )->execute();
    }

    /**
     * Items with an id are updated, new ones created, missing ones deleted.
     *
     * @param list<array{id: int|null, label: string, icon: string|null}> $items
     */
    private function syncChecklist(Task $task, array $items): void
    {
        $keep = array_filter(array_column($items, 'id'));
        TaskChecklistItem::deleteAll(['and', ['task_id' => $task->id], ['not in', 'id', $keep ?: [0]]]);
        foreach ($items as $order => $item) {
            $row = $item['id'] !== null ? TaskChecklistItem::findOne(['id' => $item['id'], 'task_id' => $task->id]) : null;
            $row ??= new TaskChecklistItem(['task_id' => $task->id]);
            $row->label = $item['label'];
            $row->icon = $item['icon'];
            $row->sort_order = $order;
            $row->save(false);
        }
    }
}
