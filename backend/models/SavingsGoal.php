<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * The reward a child is saving for (one per child).
 *
 * @property int $id
 * @property int $family_id
 * @property int $child_id
 * @property int $reward_id
 * @property int $created_at
 *
 * @property-read Reward $reward
 */
class SavingsGoal extends FamilyScopedRecord
{
    public static function tableName(): string
    {
        return '{{%savings_goal}}';
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

    public function getReward(): ActiveQuery
    {
        return $this->hasOne(Reward::class, ['id' => 'reward_id']);
    }
}
