<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\Clock;
use app\components\ErrorCode;
use app\models\ChildProfile;
use app\models\Reward;
use app\models\RewardRedemption;
use app\models\SavingsGoal;
use Yii;

/**
 * Rewards shop: requests, approvals (points debited here), limits, savings goals.
 */
class RewardService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PointsService $points,
        private readonly BadgeService $badges,
    ) {
    }

    public static function create(): self
    {
        return new self(Yii::$app->get('clock'), new PointsService(), new BadgeService());
    }

    /**
     * What the child can do with each reward right now.
     *
     * @param list<Reward> $rewards
     * @return list<array<string, mixed>>
     */
    public function shopFor(ChildProfile $child, array $rewards): array
    {
        $reserved = $this->reservedPoints($child);
        $goal = SavingsGoal::find()->forFamily($child->family_id)->andWhere(['child_id' => $child->id])->one();
        $out = [];
        foreach ($rewards as $reward) {
            $limitReached = $this->limitReached($child, $reward);
            $out[] = array_merge($reward->toArray(), [
                'affordable' => (int) $child->points_balance - $reserved >= (int) $reward->cost,
                'available' => !$limitReached && ($reward->stock === null || (int) $reward->stock > 0),
                'is_goal' => $goal !== null && (int) $goal->reward_id === (int) $reward->id,
            ]);
        }
        return $out;
    }

    public function request(ChildProfile $child, Reward $reward): RewardRedemption
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            // serialize requests of the same child
            Yii::$app->db->createCommand('SELECT id FROM {{%child_profile}} WHERE id = :id FOR UPDATE', [':id' => $child->id])->execute();
            $child->refresh();
            if (!(bool) $reward->is_active) {
                throw new ApiException(ErrorCode::REWARD_UNAVAILABLE, 'This reward is not available.', 422);
            }
            if (($reward->stock !== null && (int) $reward->stock <= 0) || $this->limitReached($child, $reward)) {
                throw new ApiException(ErrorCode::REWARD_LIMIT_REACHED, 'Limit reached for this reward.', 422);
            }
            if ((int) $child->points_balance - $this->reservedPoints($child) < (int) $reward->cost) {
                throw new ApiException(ErrorCode::INSUFFICIENT_POINTS, 'Not enough points.', 422);
            }
            $redemption = new RewardRedemption();
            $redemption->family_id = $child->family_id;
            $redemption->reward_id = $reward->id;
            $redemption->child_id = $child->id;
            $redemption->cost_snapshot = (int) $reward->cost;
            $redemption->status = RewardRedemption::STATUS_REQUESTED;
            $redemption->requested_at = $this->clock->nowDb();
            $redemption->save(false);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        NotificationService::create()->notifyParents((int) $child->family_id, 'reward_requested', [
            'name' => $child->name,
            'title' => $reward->title,
        ], '/approvals');
        return $redemption;
    }

    /**
     * @return list<array{code: string, icon: string}> badges earned
     */
    public function approve(RewardRedemption $redemption, int $parentId, ?string $note): array
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $locked = $this->lock($redemption, RewardRedemption::STATUS_REQUESTED, 'approve');
            $reward = Reward::findBySql('SELECT * FROM {{%reward}} WHERE id = :id FOR UPDATE', [':id' => $locked->reward_id])->one();
            if ($reward instanceof Reward && $reward->stock !== null) {
                if ((int) $reward->stock <= 0) {
                    throw new ApiException(ErrorCode::REWARD_LIMIT_REACHED, 'Out of stock.', 422);
                }
                $reward->updateAttributes(['stock' => (int) $reward->stock - 1]);
            }
            $child = $locked->child;
            $this->points->award($child, -(int) $locked->cost_snapshot, 0, 'reward', 'reward_redemption', (int) $locked->id, $locked->reward->title, $parentId);
            $this->review($locked, RewardRedemption::STATUS_APPROVED, $parentId, $note);
            SavingsGoal::deleteAll(['child_id' => $child->id, 'reward_id' => $locked->reward_id]);
            $badges = $this->badges->evaluate($child);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $redemption->refresh();
        return $badges;
    }

    public function reject(RewardRedemption $redemption, int $parentId, ?string $note): void
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->review($this->lock($redemption, RewardRedemption::STATUS_REQUESTED, 'reject'), RewardRedemption::STATUS_REJECTED, $parentId, $note);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $redemption->refresh();
    }

    public function fulfill(RewardRedemption $redemption): void
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->lock($redemption, RewardRedemption::STATUS_APPROVED, 'fulfill')
                ->updateAttributes(['status' => RewardRedemption::STATUS_FULFILLED, 'updated_at' => time()]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $redemption->refresh();
    }

    public function setSavingsGoal(ChildProfile $child, ?int $rewardId): void
    {
        SavingsGoal::deleteAll(['child_id' => $child->id]);
        if ($rewardId === null) {
            return;
        }
        $reward = Reward::find()->forFamily($child->family_id)->andWhere(['id' => $rewardId, 'is_active' => true])->one();
        if (!$reward instanceof Reward) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Unknown reward.', 422, 'reward_id');
        }
        $goal = new SavingsGoal();
        $goal->family_id = $child->family_id;
        $goal->child_id = $child->id;
        $goal->reward_id = $reward->id;
        $goal->save(false);
    }

    /**
     * @return array{reward_id: int, title: string, icon: string, cost: int, progress: float}|null
     */
    public function savingsGoal(ChildProfile $child): ?array
    {
        $goal = SavingsGoal::find()->forFamily($child->family_id)->andWhere(['child_id' => $child->id])->with('reward')->one();
        if (!$goal instanceof SavingsGoal) {
            return null;
        }
        $cost = max(1, (int) $goal->reward->cost);
        return [
            'reward_id' => (int) $goal->reward_id,
            'title' => $goal->reward->title,
            'icon' => $goal->reward->icon,
            'cost' => (int) $goal->reward->cost,
            'progress' => round(min(1, (int) $child->points_balance / $cost), 2),
        ];
    }

    /** Start (DATETIME, Rome) of the current period. */
    public function periodStart(string $period): string
    {
        $now = $this->clock->now();
        return match ($period) {
            'day' => $now->format('Y-m-d 00:00:00'),
            'week' => $now->modify('monday this week')->format('Y-m-d 00:00:00'),
            'month' => $now->format('Y-m-01 00:00:00'),
            default => throw new \InvalidArgumentException("Unknown period: $period"),
        };
    }

    private function limitReached(ChildProfile $child, Reward $reward): bool
    {
        if ($reward->limit_per_period === null || $reward->limit_period === null) {
            return false;
        }
        $used = RewardRedemption::find()->forFamily($child->family_id)
            ->andWhere(['reward_id' => $reward->id, 'child_id' => $child->id, 'status' => RewardRedemption::ACTIVE_STATUSES])
            ->andWhere(['>=', 'requested_at', $this->periodStart($reward->limit_period)])
            ->count();
        return (int) $used >= (int) $reward->limit_per_period;
    }

    /** Points already promised to pending requests. */
    private function reservedPoints(ChildProfile $child): int
    {
        return (int) RewardRedemption::find()->forFamily($child->family_id)
            ->andWhere(['child_id' => $child->id, 'status' => RewardRedemption::STATUS_REQUESTED])
            ->sum('cost_snapshot');
    }

    private function lock(RewardRedemption $redemption, string $expected, string $action): RewardRedemption
    {
        $locked = RewardRedemption::findBySql(
            'SELECT * FROM {{%reward_redemption}} WHERE id = :id FOR UPDATE',
            [':id' => $redemption->id]
        )->one();
        if (!$locked instanceof RewardRedemption || $locked->status !== $expected) {
            throw new ApiException(ErrorCode::INVALID_TRANSITION, "Cannot $action a {$redemption->status} request.", 422);
        }
        return $locked;
    }

    private function review(RewardRedemption $r, string $status, int $parentId, ?string $note): void
    {
        $r->status = $status;
        $r->reviewed_at = $this->clock->nowDb();
        $r->reviewed_by = $parentId;
        $r->note = $note;
        $r->save(false);
    }
}
