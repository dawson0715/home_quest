<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\FamilyContext;
use app\controllers\ApiActiveController;
use app\models\ChildProfile;
use app\models\Reward;
use app\models\RewardRedemption;
use app\services\RewardService;
use Yii;

/**
 * Rewards shop. Parents manage it; children browse it and ask for rewards.
 */
class RewardController extends ApiActiveController
{
    public $modelClass = Reward::class;
    protected array $childActions = ['index', 'redeem'];

    /**
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['delete']);
        return $actions;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return parent::verbs() + ['redeem' => ['POST']];
    }

    /**
     * Parents: all rewards (?active=1|0). Children: active rewards with
     * affordable/available/is_goal flags.
     *
     * @return list<array<string, mixed>>
     */
    public function actionIndex(): array
    {
        $this->checkAccess('index');
        $context = $this->context();
        $query = Reward::find()->forFamily()->orderBy(['sort_order' => SORT_ASC, 'cost' => SORT_ASC, 'id' => SORT_ASC]);

        $child = $context->getChild();
        if ($child === null && Yii::$app->request->get('view') === 'shop') {
            $child = $context->getPlayer(); // a parent browsing the shop as a player
        }
        if ($child instanceof ChildProfile) {
            $query->andWhere(['is_active' => true]);
            return RewardService::create()->shopFor($child, $query->all());
        }
        if (($active = Yii::$app->request->get('active')) !== null) {
            $query->andWhere(['is_active' => (bool) $active]);
        }
        return array_map(fn (Reward $r): array => $r->toArray(), $query->all());
    }

    /** Deletes a reward without history, otherwise deactivates it. */
    public function actionDelete(string $id): void
    {
        $this->checkAccess('delete');
        /** @var Reward $reward */
        $reward = $this->findFamilyModel($id, $this->action);
        if (RewardRedemption::find()->forFamily()->andWhere(['reward_id' => $reward->id])->exists()) {
            $reward->updateAttributes(['is_active' => false, 'updated_at' => time()]);
        } else {
            $reward->delete();
        }
        Yii::$app->response->statusCode = 204;
    }

    /** POST /rewards/{id}/redeem (any player) → 201 redemption `requested` */
    public function actionRedeem(string $id): RewardRedemption
    {
        $this->checkAccess('redeem');
        $context = $this->context();
        /** @var Reward $reward */
        $reward = $this->findFamilyModel($id, $this->action);
        $child = $context->getPlayer();
        $redemption = RewardService::create()->request($child, $reward);
        Yii::$app->response->statusCode = 201;
        return $redemption;
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }
}
