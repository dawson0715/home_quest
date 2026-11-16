<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * A player of the family game. Children (user_id NULL; minors: no email, no surname —
 * docs/02-architettura.md §7) and the game profile of each parent (user_id set).
 * Points/XP/level/streak are caches written only by PointsService/StreakService.
 *
 * @property int $id
 * @property int $family_id
 * @property int|null $user_id set for the game profile of a parent
 * @property string $name
 * @property string $avatar
 * @property string $color
 * @property string|null $birth_year_month
 * @property string|null $pin_hash
 * @property int $points_balance
 * @property int $xp_total
 * @property int $level
 * @property int $current_streak
 * @property int $best_streak
 * @property string|null $streak_date last day the streak was evaluated (StreakService)
 * @property bool|int $can_check_shopping
 * @property int $sort_order
 * @property string|null $archived_at
 * @property int $created_at
 * @property int $updated_at
 */
class ChildProfile extends FamilyScopedRecord
{
    /** Predefined avatar keys; the frontend maps them to images/emoji. */
    public const AVATARS = [
        'fox', 'bear', 'cat', 'dog', 'owl', 'panda', 'lion', 'rabbit',
        'unicorn', 'dragon', 'robot', 'astronaut', 'dino', 'penguin', 'koala', 'frog',
    ];

    public static function tableName(): string
    {
        return '{{%child_profile}}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * Only these attributes are writable through the API.
     *
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [['name', 'avatar', 'color'], 'required'],
            ['name', 'trim'],
            ['name', 'string', 'max' => 40],
            ['avatar', 'in', 'range' => self::AVATARS],
            ['color', 'match', 'pattern' => '/^#[0-9A-Fa-f]{6}$/'],
            ['color', 'filter', 'filter' => 'strtoupper'],
            ['birth_year_month', 'match', 'pattern' => '/^\d{4}-(0[1-9]|1[0-2])$/'],
            ['birth_year_month', 'default', 'value' => null],
            ['can_check_shopping', 'default', 'value' => false],
            ['can_check_shopping', 'boolean'],
            ['can_check_shopping', 'filter', 'filter' => 'boolval'],
            ['sort_order', 'integer', 'min' => 0, 'max' => 32767],
            ['sort_order', 'default', 'value' => 0],
        ];
    }

    public function isAdult(): bool
    {
        return $this->user_id !== null;
    }

    /** Restricts a query to children (not the parents' game profiles). */
    public static function kids(\yii\db\ActiveQuery $query, string $alias = ''): \yii\db\ActiveQuery
    {
        return $query->andWhere([($alias === '' ? '' : "$alias.") . 'user_id' => null]);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public static function isValidPin(mixed $pin): bool
    {
        return is_string($pin) && preg_match('/^\d{4}$/', $pin) === 1;
    }

    /** Sets (4 digits) or clears (null) the PIN. Caller saves. */
    public function setPin(?string $pin): void
    {
        $this->pin_hash = $pin === null ? null : Yii::$app->security->generatePasswordHash($pin, 10);
    }

    public function validatePin(string $pin): bool
    {
        return $this->pin_hash !== null && Yii::$app->security->validatePassword($pin, $this->pin_hash);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id',
            'name',
            'avatar',
            'color',
            'birth_year_month',
            'has_pin' => fn (self $c): bool => $c->hasPin(),
            'points_balance' => fn (self $c): int => (int) $c->points_balance,
            'xp_total' => fn (self $c): int => (int) $c->xp_total,
            'level' => fn (self $c): int => (int) $c->level,
            'current_streak' => fn (self $c): int => (int) $c->current_streak,
            'best_streak' => fn (self $c): int => (int) $c->best_streak,
            'can_check_shopping' => fn (self $c): bool => (bool) $c->can_check_shopping,
            'sort_order' => fn (self $c): int => (int) $c->sort_order,
            'archived' => fn (self $c): bool => $c->isArchived(),
            'is_adult' => fn (self $c): bool => $c->isAdult(),
        ];
    }

    /**
     * Minimal public shape for the device profile picker.
     *
     * @return array{id: int, name: string, avatar: string, color: string, has_pin: bool, is_adult: bool}
     */
    public function toProfile(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'color' => $this->color,
            'has_pin' => $this->hasPin(),
            'is_adult' => $this->isAdult(),
        ];
    }
}
