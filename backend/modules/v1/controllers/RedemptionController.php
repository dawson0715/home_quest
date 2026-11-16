<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\RewardRedemption;
use app\services\RewardService;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Reward requests: children see their own, parents review them.
 */
class RedemptionController extends ApiController
{
    /**
     * GET /redemptions?status=requested
     *
     * @return list<RewardRedemption>
     */
    public function actionIndex(): array
    {
        $context = $this->context();
        $query = RewardRedemption::find()->forFamily($context->getId())->with(['reward', 'child'])
            ->orderBy(['requested_at' => SORT_DESC, 'id' => SORT_DESC])->limit(200);
        $child = $context->getChild();
        if ($child !== null) {
            $query->andWhere(['child_id' => $child->id]);
        } else {
            $context->requireParent();
        }
        if (($status = Yii::$app->request->get('status')) !== null) {
            $query->andWhere(['status' => explode(',', (string) $status)]);
        }
        return $query->all();
    }

    /**
     * @return array{redemption: RewardRedemption, new_badges: list<array{code: string, icon: string}>}
     */
    public function actionApprove(string $id): array
    {
        $redemption = $this->findForParent($id);
        $badges = RewardService::create()->approve($redemption, $this->parentId(), $this->note());
        return ['redemption' => $redemption, 'new_badges' => $badges];
    }

    public function actionReject(string $id): RewardRedemption
    {
        $redemption = $this->findForParent($id);
        RewardService::create()->reject($redemption, $this->parentId(), $this->note());
        return $redemption;
    }

    public function actionFulfill(string $id): RewardRedemption
    {
        $redemption = $this->findForParent($id);
        RewardService::create()->fulfill($redemption);
        return $redemption;
    }

    private function findForParent(string $id): RewardRedemption
    {
        $context = $this->context();
        $context->requireParent();
        $redemption = RewardRedemption::find()->forFamily($context->getId())->andWhere(['id' => (int) $id])->one();
        if (!$redemption instanceof RewardRedemption) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        return $redemption;
    }

    private function note(): ?string
    {
        $note = Yii::$app->request->getBodyParam('note');
        if ($note === null || $note === '') {
            return null;
        }
        if (!is_string($note) || mb_strlen($note) > 255) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Note must be at most 255 characters.', 422, 'note');
        }
        return trim($note);
    }

    private function parentId(): int
    {
        return (int) $this->context()->getMember()->user_id;
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }
}
