<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\controllers\ApiActiveController;
use app\models\ChildProfile;
use app\models\PointTransaction;
use app\services\BadgeService;
use app\services\PointsService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\rest\IndexAction;

/**
 * Children of the family (parents only). DELETE archives the profile.
 */
class ChildController extends ApiActiveController
{
    public $modelClass = ChildProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        $actions = parent::actions();
        unset($actions['delete']);
        return $actions;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return parent::verbs() + ['pin' => ['PUT'], 'adjust' => ['POST'], 'transactions' => ['GET']];
    }

    /** ?archived=1 lists archived profiles instead of active ones. */
    public function prepareFamilyDataProvider(IndexAction $action, mixed $filter): ActiveDataProvider
    {
        $query = ChildProfile::find()->forFamily()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
        if (Yii::$app->request->get('players')) {
            // every player: children + parents' game profiles
            return new ActiveDataProvider(['query' => $query->andWhere(['archived_at' => null]), 'pagination' => false]);
        }
        ChildProfile::kids($query);
        if (Yii::$app->request->get('archived')) {
            $query->andWhere(['not', ['archived_at' => null]]);
        } else {
            $query->andWhere(['archived_at' => null]);
        }
        return new ActiveDataProvider(['query' => $query, 'pagination' => false]);
    }

    /**
     * Parents' game profiles are reachable for view/update/transactions/adjust
     * but cannot be archived or get a PIN (they belong to the parent's account).
     */
    public function findFamilyModel(string $id, \yii\base\Action $action): \app\models\FamilyScopedRecord
    {
        /** @var ChildProfile $profile */
        $profile = parent::findFamilyModel($id, $action);
        if ($profile->isAdult() && in_array($action->id, ['delete', 'pin'], true)) {
            throw new \yii\web\NotFoundHttpException("Object not found: $id");
        }
        return $profile;
    }

    /** Archives (soft-deletes) the child: history is kept. */
    public function actionDelete(string $id): void
    {
        $this->checkAccess('delete');
        /** @var ChildProfile $child */
        $child = $this->findFamilyModel($id, $this->action);
        if (!$child->isArchived()) {
            $child->archived_at = Yii::$app->get('clock')->nowDb();
            $child->save(false, ['archived_at', 'updated_at']);
        }
        Yii::$app->response->statusCode = 204;
    }

    /** GET /children/{id}/transactions — points ledger, newest first (paginated). */
    public function actionTransactions(string $id): ActiveDataProvider
    {
        $this->checkAccess('transactions');
        /** @var ChildProfile $child */
        $child = $this->findFamilyModel($id, $this->action);
        return self::ledger($child);
    }

    /**
     * POST /children/{id}/adjust {type: bonus|penalty, points, xp?, note}
     *
     * @return array{child: ChildProfile, transaction: \app\models\PointTransaction, level_up: bool}
     */
    public function actionAdjust(string $id): array
    {
        $this->checkAccess('adjust');
        /** @var ChildProfile $child */
        $child = $this->findFamilyModel($id, $this->action);
        $context = Yii::$app->get('familyContext');
        $body = Yii::$app->request;
        $xp = $body->getBodyParam('xp');
        $result = (new PointsService())->adjust(
            $child,
            (string) $body->getBodyParam('type', ''),
            (int) $body->getBodyParam('points', 0),
            $xp === null ? null : (int) $xp,
            (string) $body->getBodyParam('note', ''),
            (int) $context->getMember()->user_id,
            (bool) ($context->getFamily()->getEffectiveSettings()['allow_penalties'] ?? false),
        );
        (new BadgeService())->evaluate($child);
        Yii::$app->response->statusCode = 201;
        return ['child' => $child, 'transaction' => $result->transaction, 'level_up' => $result->levelUp];
    }

    public static function ledger(ChildProfile $child): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => PointTransaction::find()->forFamily($child->family_id)->andWhere(['child_id' => $child->id])
                ->orderBy(['id' => SORT_DESC]),
            'pagination' => ['defaultPageSize' => 20, 'pageSizeLimit' => [1, 100]],
        ]);
    }

    /** PUT /children/{id}/pin {pin: "1234"|null} */
    public function actionPin(string $id): ChildProfile
    {
        $this->checkAccess('pin');
        /** @var ChildProfile $child */
        $child = $this->findFamilyModel($id, $this->action);
        $pin = Yii::$app->request->getBodyParam('pin');
        if ($pin !== null && !ChildProfile::isValidPin($pin)) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'PIN must be 4 digits.', 422, 'pin');
        }
        $child->setPin($pin);
        $child->save(false, ['pin_hash', 'updated_at']);
        return $child;
    }
}
