<?php

declare(strict_types=1);

namespace app\models;

use yii\base\InvalidCallException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Immutable points ledger: written only by PointsService; corrections are new rows.
 *
 * @property int $id
 * @property int $family_id
 * @property int $child_id
 * @property int $points
 * @property int $xp
 * @property string $type
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property int|null $created_by
 * @property int $created_at
 */
class PointTransaction extends FamilyScopedRecord
{
    public const TYPES = ['task', 'bonus', 'penalty', 'reward', 'streak_bonus', 'badge', 'adjustment'];

    public static function tableName(): string
    {
        return '{{%point_transaction}}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [['class' => TimestampBehavior::class, 'updatedAtAttribute' => false]];
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function beforeSave($insert): bool
    {
        if (!$insert) {
            throw new InvalidCallException('Point transactions are immutable.');
        }
        return parent::beforeSave($insert);
    }

    public function beforeDelete(): bool
    {
        throw new InvalidCallException('Point transactions are immutable.');
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $t): int => (int) $t->id,
            'child_id' => fn (self $t): int => (int) $t->child_id,
            'points' => fn (self $t): int => (int) $t->points,
            'xp' => fn (self $t): int => (int) $t->xp,
            'type',
            'source_type',
            'source_id' => fn (self $t): ?int => $t->source_id === null ? null : (int) $t->source_id,
            'note',
            'created_at' => fn (self $t): string => date(DATE_ATOM, (int) $t->created_at),
        ];
    }
}
