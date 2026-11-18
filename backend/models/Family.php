<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Tenant: every domain record belongs to one family.
 *
 * @property int $id
 * @property string $name
 * @property array<string, mixed>|null $settings
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read FamilyMember[] $members
 */
class Family extends ActiveRecord
{
    public const MEAL_TYPES = ['breakfast', 'lunch', 'snack', 'dinner'];

    /** @var array{allow_penalties: bool, weekly_email: bool, meal_types: list<string>, week_starts_on: int, shopping_poll_seconds: int, ai_suggestions: bool} */
    public const DEFAULT_SETTINGS = [
        'allow_penalties' => false,
        'weekly_email' => false,
        'meal_types' => self::MEAL_TYPES,
        'week_starts_on' => 1,
        'shopping_poll_seconds' => 5,
        // AI menu suggestions (anonymous data sent to the configured provider); parents can turn it off
        'ai_suggestions' => true,
    ];

    public static function tableName(): string
    {
        return '{{%family}}';
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
        return [
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 80],
            ['settings', 'validateSettings'],
        ];
    }

    /**
     * Settings are merged over the current/default ones; unknown keys are rejected.
     */
    public function validateSettings(string $attribute): void
    {
        $value = $this->$attribute;
        if ($value === null) {
            return;
        }
        if (!is_array($value)) {
            $this->addError($attribute, 'Settings must be an object.');
            return;
        }
        foreach (array_keys($value) as $key) {
            if (!array_key_exists($key, self::DEFAULT_SETTINGS)) {
                $this->addError($attribute, "Unknown setting \"$key\".");
            }
        }
        foreach (['allow_penalties', 'weekly_email', 'ai_suggestions'] as $flag) {
            if (array_key_exists($flag, $value) && !is_bool($value[$flag])) {
                $this->addError($attribute, "$flag must be a boolean.");
            }
        }
        if (array_key_exists('meal_types', $value)) {
            $types = $value['meal_types'];
            if (!is_array($types) || $types === [] || array_diff($types, self::MEAL_TYPES) !== []
                || count(array_unique($types)) !== count($types)) {
                $this->addError($attribute, 'meal_types must be a non-empty subset of: ' . implode(', ', self::MEAL_TYPES) . '.');
            }
        }
        if (array_key_exists('week_starts_on', $value) && !in_array($value['week_starts_on'], [1, 7], true)) {
            $this->addError($attribute, 'week_starts_on must be 1 (Monday) or 7 (Sunday).');
        }
        if (array_key_exists('shopping_poll_seconds', $value)) {
            $s = $value['shopping_poll_seconds'];
            if (!is_int($s) || $s < 2 || $s > 60) {
                $this->addError($attribute, 'shopping_poll_seconds must be an integer between 2 and 60.');
            }
        }
    }

    /**
     * Effective settings (defaults + stored overrides).
     *
     * @return array<string, mixed>
     */
    public function getEffectiveSettings(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    public function getMembers(): ActiveQuery
    {
        return $this->hasMany(FamilyMember::class, ['family_id' => 'id']);
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id',
            'name',
            'settings' => fn (self $f): array => $f->getEffectiveSettings(),
        ];
    }
}
