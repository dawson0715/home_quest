<?php

declare(strict_types=1);

namespace app\forms;

use app\models\ChildProfile;
use app\models\MealPlanWeek;
use app\models\MealSlot;
use app\models\Task;
use app\models\TaskChecklistItem;
use yii\base\Model;

/**
 * Input for POST /tasks and PATCH /tasks/{id}. Only keys present in the payload
 * are applied (PATCH semantics). Persisted by TaskService.
 */
class TaskForm extends Model
{
    public const TASK_ATTRIBUTES = [
        'title', 'description', 'icon', 'points', 'xp', 'requires_approval', 'recurrence_type',
        'recurrence_days', 'interval_days', 'time_of_day', 'start_date', 'end_date', 'due_date', 'is_active',
        'linked_meal_slot_id',
    ];
    public const MAX_CHECKLIST_ITEMS = 30;

    /** @var list<int>|null */
    public ?array $assigneeIds = null;
    /** @var list<array{id: int|null, label: string, icon: string|null}>|null */
    public ?array $checklist = null;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(public readonly Task $task, private readonly string $today, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * @param mixed $data request body
     */
    public function load($data, $formName = ''): bool
    {
        $this->data = is_array($data) ? $data : [];
        return true;
    }

    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        if ($clearErrors) {
            $this->clearErrors();
        }
        $task = $this->task;
        $isNew = $task->isNewRecord;

        foreach (self::TASK_ATTRIBUTES as $attribute) {
            if (array_key_exists($attribute, $this->data)) {
                $task->$attribute = $this->data[$attribute];
            }
        }
        if ($isNew && empty($task->start_date)) {
            $task->start_date = $this->today;
        }
        if ($isNew && !array_key_exists('xp', $this->data)) {
            // default xp = points (Task rule)
            $task->setAttribute('xp', null);
        }
        // recurrence fields that do not apply are cleared
        if ($task->recurrence_type !== Task::RECURRENCE_WEEKLY) {
            $task->recurrence_days = null;
        }
        if ($task->recurrence_type !== Task::RECURRENCE_INTERVAL) {
            $task->interval_days = null;
        }
        if ($task->recurrence_type !== Task::RECURRENCE_NONE) {
            $task->due_date = null;
        }

        if (!$task->validate()) {
            $this->addErrors($task->getErrors());
        }
        if (array_key_exists('linked_meal_slot_id', $this->data)) {
            $this->validateMealSlot();
        }
        if ($isNew || array_key_exists('assignee_ids', $this->data)) {
            $this->validateAssignees($this->data['assignee_ids'] ?? null);
        }
        if (array_key_exists('checklist', $this->data)) {
            $this->validateChecklist($this->data['checklist']);
        } elseif ($isNew) {
            $this->checklist = [];
        }

        return !$this->hasErrors();
    }

    /** The linked meal slot ("help cooking") must belong to the family. */
    private function validateMealSlot(): void
    {
        $slotId = $this->data['linked_meal_slot_id'] ?? null;
        if ($slotId === null || $slotId === '') {
            $this->task->linked_meal_slot_id = null;
            return;
        }
        $exists = MealSlot::find()->alias('s')
            ->innerJoin(MealPlanWeek::tableName() . ' w', 'w.id = s.week_id')
            ->where(['s.id' => (int) $slotId, 'w.family_id' => $this->task->family_id])
            ->exists();
        if (!$exists) {
            $this->addError('linked_meal_slot_id', 'Unknown meal slot.');
        }
    }

    private function validateAssignees(mixed $ids): void
    {
        if (!is_array($ids) || $ids === []) {
            $this->addError('assignee_ids', 'Assign the task to at least one child.');
            return;
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $valid = ChildProfile::find()->forFamily($this->task->family_id ?: null)
            ->andWhere(['id' => $ids, 'archived_at' => null])->select('id')->column();
        if (count($valid) !== count($ids)) {
            $this->addError('assignee_ids', 'Unknown or archived child.');
            return;
        }
        $this->assigneeIds = $ids;
    }

    private function validateChecklist(mixed $items): void
    {
        if (!is_array($items)) {
            $this->addError('checklist', 'Checklist must be a list.');
            return;
        }
        if (count($items) > self::MAX_CHECKLIST_ITEMS) {
            $this->addError('checklist', 'Too many checklist items (max ' . self::MAX_CHECKLIST_ITEMS . ').');
            return;
        }
        $existing = $this->task->isNewRecord ? [] : array_map('intval', TaskChecklistItem::find()
            ->where(['task_id' => $this->task->id])->select('id')->column());
        $clean = [];
        foreach (array_values($items) as $i => $item) {
            $probe = new TaskChecklistItem([
                'label' => is_array($item) ? ($item['label'] ?? null) : null,
                'icon' => is_array($item) ? ($item['icon'] ?? null) : null,
            ]);
            $id = is_array($item) && isset($item['id']) ? (int) $item['id'] : null;
            if (!$probe->validate(['label', 'icon'])) {
                $this->addError('checklist', "Item $i: " . implode(' ', $probe->getFirstErrors()));
                continue;
            }
            if ($id !== null && !in_array($id, $existing, true)) {
                $this->addError('checklist', "Item $i: unknown id $id.");
                continue;
            }
            $clean[] = ['id' => $id, 'label' => (string) $probe->label, 'icon' => $probe->icon];
        }
        $this->checklist = $clean;
    }
}
