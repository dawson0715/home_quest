<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * A child's request for a reward. Points are debited only on approval.
 *
 * @property int $id
 * @property int $family_id
 * @property int $reward_id
 * @property int $child_id
 * @property int $cost_snapshot
 * @property string $status requested|approved|rejected|fulfilled
 * @property string $requested_at
 * @property string|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $note
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Reward $reward
 * @property-read ChildProfile $child
 */
class RewardRedemption extends FamilyScopedRecord
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FULFILLED = 'fulfilled';
    /** Statuses that count against period limits and stock. */
    public const ACTIVE_STATUSES = [self::STATUS_REQUESTED, self::STATUS_APPROVED, self::STATUS_FULFILLED];

    public static function tableName(): string
    {
        return '{{%reward_redemption}}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function getReward(): ActiveQuery
    {
        return $this->hasOne(Reward::class, ['id' => 'reward_id']);
    }

    public function getChild(): ActiveQuery
    {
        return $this->hasOne(ChildProfile::class, ['id' => 'child_id']);
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $r): int => (int) $r->id,
            'reward_id' => fn (self $r): int => (int) $r->reward_id,
            'child_id' => fn (self $r): int => (int) $r->child_id,
            'title' => fn (self $r): string => $r->reward->title,
            'icon' => fn (self $r): string => $r->reward->icon,
            'cost' => fn (self $r): int => (int) $r->cost_snapshot,
            'status',
            'requested_at',
            'reviewed_at',
            'note',
            'child' => fn (self $r): array => $r->child->toProfile(),
        ];
    }
}
