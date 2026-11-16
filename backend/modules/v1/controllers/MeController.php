<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\ChildProfile;
use app\models\ChildSession;
use app\models\Device;
use app\models\FamilyMember;
use app\models\Task;
use app\models\User;
use app\services\PointsService;
use app\services\RewardService;
use app\services\TaskOccurrenceService;
use yii\data\ActiveDataProvider;
use Yii;

/**
 * Who am I: parent user, paired device or child session.
 */
class MeController extends ApiController
{
    /**
     * @return array<string, mixed>
     */
    public function actionIndex(): array
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $actor = $context->getActor();

        if ($actor instanceof ChildSession) {
            return ['child' => $actor->child, 'family' => $context->getFamily(), 'role' => FamilyContext::ROLE_CHILD];
        }
        if ($actor instanceof Device) {
            return [
                'device' => ['id' => $actor->id, 'name' => $actor->name],
                'family' => $context->getFamily(),
                'role' => FamilyContext::ROLE_DEVICE,
            ];
        }

        /** @var User $actor */
        return $this->parentMe($actor);
    }

    /**
     * GET /me/today (any player: child or parent): today's tasks grouped by time of day.
     *
     * @return array<string, mixed>
     */
    public function actionToday(): array
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $child = $context->getPlayer();

        $groups = array_fill_keys(Task::TIMES_OF_DAY, []);
        foreach (TaskOccurrenceService::create()->today($child) as $occurrence) {
            $groups[$occurrence->task->time_of_day][] = $occurrence;
        }

        $child->refresh();
        return [
            'date' => Yii::$app->get('clock')->today(),
            'child' => array_merge($child->toArray(), [
                'xp_level_start' => PointsService::xpForLevel((int) $child->level),
                'xp_next_level' => PointsService::xpForLevel((int) $child->level + 1),
            ]),
            'groups' => $groups,
            'savings_goal' => RewardService::create()->savingsGoal($child),
        ];
    }

    /**
     * PUT /me/savings-goal {reward_id|null} (child)
     *
     * @return array{savings_goal: array<string, mixed>|null}
     */
    public function actionSavingsGoal(): array
    {
        $child = $this->requireChild();
        $rewardId = Yii::$app->request->getBodyParam('reward_id');
        $service = RewardService::create();
        $service->setSavingsGoal($child, $rewardId === null ? null : (int) $rewardId);
        return ['savings_goal' => $service->savingsGoal($child)];
    }

    /** GET /me/transactions (child): own points ledger. */
    public function actionTransactions(): ActiveDataProvider
    {
        return ChildController::ledger($this->requireChild());
    }

    private function requireChild(): ChildProfile
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        return $context->getPlayer();
    }

    /**
     * @return array<string, mixed>
     */
    private function parentMe(User $actor): array
    {
        $member = FamilyMember::find()->with('family')->where(['user_id' => $actor->id])->one();
        $player = $member === null ? null : ChildProfile::find()->forFamily($member->family_id)->andWhere(['user_id' => $actor->id])->one();
        return [
            'user' => $actor,
            'family' => $member?->family,
            'role' => $member?->role,
            'player' => $player,
        ];
    }
}
