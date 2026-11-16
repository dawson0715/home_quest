<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\FamilyScopedRecord;
use app\components\ApiBehaviors;
use app\rest\Serializer;
use Yii;
use yii\base\Action;
use yii\data\ActiveDataProvider;
use yii\rest\ActiveController;
use yii\rest\IndexAction;
use yii\web\NotFoundHttpException;

/**
 * Base for CRUD controllers. When modelClass is a FamilyScopedRecord, index/view/
 * update/delete only ever see the caller's family (others → 404). By default every
 * action is parent-only; list in $childActions the actions children may call.
 * Subclasses overriding prepareDataProvider/findModel MUST keep using ->forFamily().
 */
class ApiActiveController extends ActiveController
{
    /** @var list<string> action ids also allowed to child sessions */
    protected array $childActions = [];

    use EnvelopeMetaTrait;

    /** @var string|array<string, mixed> */
    public $serializer = Serializer::class;

    /**
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return ApiBehaviors::apply(parent::behaviors());
    }

    /**
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        $actions = parent::actions();
        if ($this->isFamilyScoped()) {
            foreach (['view', 'update', 'delete'] as $id) {
                $actions[$id]['findModel'] = [$this, 'findFamilyModel'];
            }
            $actions['index']['prepareDataProvider'] = [$this, 'prepareFamilyDataProvider'];
        }
        return $actions;
    }

    /**
     * @param string $action
     * @param object|null $model
     * @param array<string, mixed> $params
     */
    public function checkAccess($action, $model = null, $params = []): void
    {
        $context = Yii::$app->get('familyContext');
        $context->getId(); // 401 / 403 no_family before touching any data
        if (in_array($action, $this->childActions, true)) {
            $context->requireRole(...[...$context::PARENT_ROLES, $context::ROLE_CHILD]);
        } else {
            $context->requireParent();
        }
    }

    public function findFamilyModel(string $id, Action $action): FamilyScopedRecord
    {
        /** @var class-string<FamilyScopedRecord> $class */
        $class = $this->modelClass;
        $model = $class::find()->forFamily()->andWhere([$class::tableName() . '.id' => $id])->one();
        if (!$model instanceof FamilyScopedRecord) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        return $model;
    }

    public function prepareFamilyDataProvider(IndexAction $action, mixed $filter): ActiveDataProvider
    {
        /** @var class-string<FamilyScopedRecord> $class */
        $class = $this->modelClass;
        $query = $class::find()->forFamily();
        if (!empty($filter)) {
            $query->andWhere($filter);
        }
        return new ActiveDataProvider(['query' => $query]);
    }

    private function isFamilyScoped(): bool
    {
        return is_subclass_of($this->modelClass, FamilyScopedRecord::class);
    }
}
