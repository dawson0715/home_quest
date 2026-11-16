<?php

declare(strict_types=1);

namespace app\services;

use app\models\ChildProfile;
use app\models\Task;
use app\models\TaskOccurrence;

/**
 * Daily streaks: a day is perfect when every recurring occurrence of the child
 * was done (approved, or submitted and waiting). Days without routines neither
 * extend nor break the streak. Bonus points at 3, 7, 14 and 30 days.
 */
class StreakService
{
    /** streak length => bonus points (and XP) */
    public const BONUSES = [3 => 10, 7 => 25, 14 => 50, 30 => 100];

    public function __construct(
        private readonly PointsService $points,
        private readonly BadgeService $badges,
    ) {
    }

    public static function create(): self
    {
        return new self(new PointsService(), new BadgeService());
    }

    /**
     * Evaluates $day for every active child not yet evaluated for it (idempotent).
     *
     * @return int children whose streak was evaluated
     */
    public function evaluateDay(string $day): int
    {
        $count = 0;
        $children = ChildProfile::find()
            ->where(['archived_at' => null])
            ->andWhere(['or', ['streak_date' => null], ['<', 'streak_date', $day]]);
        foreach ($children->each(100) as $child) {
            /** @var ChildProfile $child */
            $this->evaluateChild($child, $day);
            $count++;
        }
        return $count;
    }

    public function evaluateChild(ChildProfile $child, string $day): void
    {
        $rows = TaskOccurrence::find()->alias('o')
            ->forFamily($child->family_id)
            ->innerJoinWith('task t', false)
            ->andWhere(['o.child_id' => $child->id, 'o.due_date' => $day])
            ->andWhere(['not', ['t.recurrence_type' => Task::RECURRENCE_NONE]])
            ->andWhere(['t.is_open' => false])
            ->select('o.status')
            ->column();

        $current = (int) $child->current_streak;
        $best = (int) $child->best_streak;
        if ($rows !== []) {
            $done = array_filter($rows, fn (string $s): bool => in_array($s, [TaskOccurrence::STATUS_APPROVED, TaskOccurrence::STATUS_SUBMITTED], true));
            $current = count($done) === count($rows) ? $current + 1 : 0;
            $best = max($best, $current);
        }
        $child->updateAttributes(['current_streak' => $current, 'best_streak' => $best, 'streak_date' => $day]);

        if ($rows !== [] && isset(self::BONUSES[$current])) {
            $bonus = self::BONUSES[$current];
            $this->points->award($child, $bonus, $bonus, 'streak_bonus', 'streak', $current, "Streak $current");
        }
        if ($rows !== []) {
            $this->badges->evaluate($child);
        }
    }
}
