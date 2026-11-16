<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\controllers\ApiActiveController;
use app\forms\TaskForm;
use app\models\Task;
use app\services\TaskService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\rest\IndexAction;

/**
 * Tasks and routines (parents only).
 */
class TaskController extends ApiActiveController
{
    public $modelClass = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        $actions = parent::actions();
        unset($actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /** ?child_id= filters by assignee, ?active=1|0 by status (default: all). */
    public function prepareFamilyDataProvider(IndexAction $action, mixed $filter): ActiveDataProvider
    {
        $request = Yii::$app->request;
        $query = Task::find()->forFamily()->with('checklistItems')
            ->orderBy(['is_active' => SORT_DESC, 'time_of_day' => SORT_ASC, 'title' => SORT_ASC]);
        if (($childId = $request->get('child_id')) !== null) {
            $query->andWhere(['id' => (new \yii\db\Query())->select('task_id')->from('{{%task_assignee}}')->where(['child_id' => (int) $childId])]);
        }
        if (($active = $request->get('active')) !== null) {
            $query->andWhere(['is_active' => (bool) $active]);
        }
        return new ActiveDataProvider(['query' => $query, 'pagination' => false]);
    }

    public function actionCreate(): Task|TaskForm
    {
        $this->checkAccess('create');
        $service = TaskService::create();
        $form = $service->newForm();
        $form->load((array) Yii::$app->request->getBodyParams());
        if (!$service->save($form, $this->userId())) {
            return $form;
        }
        Yii::$app->response->statusCode = 201;
        return $form->task;
    }

    public function actionUpdate(string $id): Task|TaskForm
    {
        $this->checkAccess('update');
        /** @var Task $task */
        $task = $this->findFamilyModel($id, $this->action);
        $service = TaskService::create();
        $form = $service->newForm($task);
        $form->load((array) Yii::$app->request->getBodyParams());
        return $service->save($form, $this->userId()) ? $form->task : $form;
    }

    public function actionDelete(string $id): void
    {
        $this->checkAccess('delete');
        /** @var Task $task */
        $task = $this->findFamilyModel($id, $this->action);
        TaskService::create()->delete($task);
        Yii::$app->response->statusCode = 204;
    }

    private function userId(): ?int
    {
        $id = Yii::$app->user->id;
        return is_numeric($id) ? (int) $id : null;
    }
}
