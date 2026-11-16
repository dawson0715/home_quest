<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\ErrorCode;
use app\models\ChildProfile;
use app\models\PointTransaction;
use Yii;

/**
 * The only way points/XP move (CLAUDE.md rule 3): every change is a
 * point_transaction row plus the cached totals on child_profile, in one
 * DB transaction with the child row locked.
 *
 * Levels: level n → n+1 needs 100 × n XP, i.e. level L starts at 50·L·(L−1) XP.
 */
class PointsService
{
    /**
     * Credits (or with negative $points debits) a child.
     *
     * @throws ApiException 422 insufficient_points if the balance would go negative
     */
    public function award(
        ChildProfile $child,
        int $points,
        int $xp,
        string $type,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $createdBy = null,
    ): PointsResult {
        if ($xp < 0) {
            throw new \InvalidArgumentException('XP never decreases.');
        }
        if (!in_array($type, PointTransaction::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown transaction type: $type");
        }

        $db = Yii::$app->db;
        $outer = $db->getTransaction();
        $tx = $outer === null ? $db->beginTransaction() : null;
        try {
            $row = $db->createCommand(
                'SELECT points_balance, xp_total, level FROM {{%child_profile}} WHERE id = :id FOR UPDATE',
                [':id' => $child->id]
            )->queryOne();
            if ($row === false) {
                throw new \RuntimeException("Child {$child->id} not found.");
            }

            $balance = (int) $row['points_balance'] + $points;
            if ($balance < 0) {
                throw new ApiException(ErrorCode::INSUFFICIENT_POINTS, 'Not enough points.', 422);
            }
            $xpTotal = (int) $row['xp_total'] + $xp;
            $oldLevel = (int) $row['level'];
            $level = max($oldLevel, self::levelForXp($xpTotal));

            $transaction = new PointTransaction();
            $transaction->family_id = $child->family_id;
            $transaction->child_id = $child->id;
            $transaction->points = $points;
            $transaction->xp = $xp;
            $transaction->type = $type;
            $transaction->source_type = $sourceType;
            $transaction->source_id = $sourceId;
            $transaction->note = $note;
            $transaction->created_by = $createdBy;
            $transaction->save(false);

            $db->createCommand()->update(
                '{{%child_profile}}',
                ['points_balance' => $balance, 'xp_total' => $xpTotal, 'level' => $level, 'updated_at' => time()],
                ['id' => $child->id]
            )->execute();
            $tx?->commit();
        } catch (\Throwable $e) {
            $tx?->rollBack();
            throw $e;
        }

        $child->points_balance = $balance;
        $child->xp_total = $xpTotal;
        $child->level = $level;
        $child->setOldAttributes(array_merge($child->getOldAttributes(), [
            'points_balance' => $balance, 'xp_total' => $xpTotal, 'level' => $level,
        ]));

        return new PointsResult($transaction, $balance, $xpTotal, $level, $level > $oldLevel);
    }

    /**
     * Manual bonus (positive) or penalty (negative points, no XP) by a parent.
     *
     * @throws ApiException penalties_disabled / validation_failed / insufficient_points
     */
    public function adjust(ChildProfile $child, string $type, int $points, ?int $xp, string $note, int $parentId, bool $penaltiesAllowed): PointsResult
    {
        if (!in_array($type, ['bonus', 'penalty'], true)) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Type must be bonus or penalty.', 422, 'type');
        }
        if ($points < 1 || $points > 1000) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Points must be between 1 and 1000.', 422, 'points');
        }
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 255) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'A reason (max 255 characters) is required.', 422, 'note');
        }
        if ($type === 'penalty') {
            if (!$penaltiesAllowed) {
                throw new ApiException(ErrorCode::PENALTIES_DISABLED, 'Penalties are disabled for this family.', 422);
            }
            return $this->award($child, -$points, 0, 'penalty', null, null, $note, $parentId);
        }
        $xp ??= $points;
        if ($xp < 0 || $xp > 1000) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'XP must be between 0 and 1000.', 422, 'xp');
        }
        return $this->award($child, $points, $xp, 'bonus', null, null, $note, $parentId);
    }

    /** XP needed to reach $level (level 1 = 0, 2 = 100, 3 = 300, 4 = 600 …). */
    public static function xpForLevel(int $level): int
    {
        return 50 * $level * ($level - 1);
    }

    public static function levelForXp(int $xp): int
    {
        $level = 1;
        while (self::xpForLevel($level + 1) <= $xp) {
            $level++;
        }
        return $level;
    }
}
