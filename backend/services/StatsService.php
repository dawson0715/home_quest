<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\ErrorCode;
use app\models\ChildProfile;
use app\models\TaskOccurrence;
use yii\db\Query;

/**
 * Per-child statistics over a date range (max 92 days).
 */
class StatsService
{
    public const MAX_DAYS = 92;

    public function __construct(private readonly int $familyId)
    {
    }

    /**
     * @return array{from: string, to: string, children: list<array<string, mixed>>}
     */
    public function compute(string $from, string $to, ?int $childId = null): array
    {
        foreach (['from' => $from, 'to' => $to] as $field => $date) {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($d === false || $d->format('Y-m-d') !== $date) {
                throw new ApiException(ErrorCode::VALIDATION_FAILED, "$field must be Y-m-d.", 422, $field);
            }
        }
        if ($from > $to || RecurrenceRule::daysBetween($from, $to) >= self::MAX_DAYS) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Range must be 1–' . self::MAX_DAYS . ' days.', 422, 'to');
        }

        $children = ChildProfile::find()->forFamily($this->familyId)->andWhere(['archived_at' => null])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
        if ($childId !== null) {
            $children->andWhere(['id' => $childId]);
        }

        $out = [];
        foreach ($children->all() as $child) {
            $out[] = $this->forChild($child, $from, $to);
        }
        return ['from' => $from, 'to' => $to, 'children' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    private function forChild(ChildProfile $child, string $from, string $to): array
    {
        $statuses = (new Query())->from(TaskOccurrence::tableName())
            ->where(['family_id' => $this->familyId, 'child_id' => $child->id])
            ->andWhere(['between', 'due_date', $from, $to])
            ->groupBy('status')->select(['n' => 'COUNT(*)', 'status'])->indexBy('status')->column();
        $count = fn (string $s): int => (int) ($statuses[$s] ?? 0);

        $perDay = [];
        for ($d = $from; $d <= $to; $d = (new \DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d')) {
            $perDay[$d] = ['date' => $d, 'approved' => 0, 'missed' => 0];
        }
        $rows = (new Query())->from(TaskOccurrence::tableName())
            ->where(['family_id' => $this->familyId, 'child_id' => $child->id, 'status' => ['approved', 'missed']])
            ->andWhere(['between', 'due_date', $from, $to])
            ->groupBy(['due_date', 'status'])->select(['due_date', 'status', 'n' => 'COUNT(*)'])->all();
        foreach ($rows as $row) {
            $perDay[(string) $row['due_date']][(string) $row['status']] = (int) $row['n'];
        }

        $start = (new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone(\Yii::$app->timeZone)))->getTimestamp();
        $end = (new \DateTimeImmutable($to . ' 23:59:59', new \DateTimeZone(\Yii::$app->timeZone)))->getTimestamp();
        $points = (new Query())->from('{{%point_transaction}}')
            ->where(['family_id' => $this->familyId, 'child_id' => $child->id])
            ->andWhere(['between', 'created_at', $start, $end])
            ->select(['earned' => 'COALESCE(SUM(CASE WHEN points > 0 THEN points END), 0)', 'spent' => 'COALESCE(-SUM(CASE WHEN points < 0 THEN points END), 0)'])
            ->one();

        $approved = $count('approved');
        $missed = $count('missed');
        return [
            'child' => $child->toProfile(),
            'tasks' => [
                'approved' => $approved,
                'missed' => $missed,
                'rejected' => $count('rejected'),
                'submitted' => $count('submitted'),
                'pending' => $count('pending'),
            ],
            'completion_rate' => $approved + $missed === 0 ? null : round($approved / ($approved + $missed), 2),
            'points_earned' => (int) ($points['earned'] ?? 0),
            'points_spent' => (int) ($points['spent'] ?? 0),
            'current_streak' => (int) $child->current_streak,
            'best_streak' => (int) $child->best_streak,
            'per_day' => array_values($perDay),
        ];
    }
}
