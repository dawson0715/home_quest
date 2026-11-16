<?php

declare(strict_types=1);

namespace app\services;

use app\models\Badge;
use app\models\ChildProfile;
use app\models\RewardRedemption;
use app\models\TaskOccurrence;
use Yii;
use yii\db\Query;

/**
 * Awards badges whose rule (badge.rule JSON) the child now satisfies.
 *
 * Rule types: tasks_completed, streak (best streak), level, rewards_redeemed,
 * time_of_day_completed {time_of_day}, meal_tasks_completed.
 */
class BadgeService
{
    /**
     * @return list<array{code: string, icon: string}> badges earned now
     */
    public function evaluate(ChildProfile $child): array
    {
        $earned = array_map('intval', (new Query())->select('badge_id')->from('{{%child_badge}}')
            ->where(['child_id' => $child->id])->column());
        $metrics = [];
        $new = [];
        $now = Yii::$app->get('clock')->nowDb();

        /** @var Badge $badge */
        foreach (Badge::find()->orderBy(['sort_order' => SORT_ASC])->all() as $badge) {
            if (in_array((int) $badge->id, $earned, true)) {
                continue;
            }
            $rule = $badge->rule;
            $key = $rule['type'] . ':' . ($rule['time_of_day'] ?? '');
            $metrics[$key] ??= $this->metric($child, $rule);
            if ($metrics[$key] >= (int) $rule['threshold']) {
                $inserted = Yii::$app->db->createCommand(
                    'INSERT IGNORE INTO {{%child_badge}} (child_id, badge_id, earned_at) VALUES (:c, :b, :t)',
                    [':c' => $child->id, ':b' => $badge->id, ':t' => $now]
                )->execute();
                if ($inserted > 0) {
                    $new[] = ['code' => $badge->code, 'icon' => $badge->icon];
                }
            }
        }
        return $new;
    }

    /**
     * @param array{type: string, threshold: int, time_of_day?: string} $rule
     */
    private function metric(ChildProfile $child, array $rule): int
    {
        $approved = fn (): \yii\db\ActiveQuery => TaskOccurrence::find()->alias('o')
            ->forFamily($child->family_id)
            ->andWhere(['o.child_id' => $child->id, 'o.status' => TaskOccurrence::STATUS_APPROVED]);

        return match ($rule['type']) {
            'tasks_completed' => (int) $approved()->count(),
            'time_of_day_completed' => (int) $approved()->innerJoinWith('task t')
                ->andWhere(['t.time_of_day' => $rule['time_of_day'] ?? 'any'])->count(),
            'meal_tasks_completed' => (int) $approved()->innerJoinWith('task t')
                ->andWhere(['not', ['t.linked_meal_slot_id' => null]])->count(),
            'streak' => max((int) $child->best_streak, (int) $child->current_streak),
            'level' => (int) $child->level,
            'rewards_redeemed' => (int) RewardRedemption::find()->forFamily($child->family_id)
                ->andWhere(['child_id' => $child->id, 'status' => [RewardRedemption::STATUS_APPROVED, RewardRedemption::STATUS_FULFILLED]])
                ->count(),
            default => 0,
        };
    }
}
