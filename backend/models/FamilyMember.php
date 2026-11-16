<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * Adult member of a family (a user belongs to exactly one family).
 *
 * @property int $id
 * @property int $family_id
 * @property int $user_id
 * @property string $role
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Family $family
 * @property-read User $user
 */
class FamilyMember extends FamilyScopedRecord
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_COPARENT = 'coparent';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_COPARENT];

    public static function tableName(): string
    {
        return '{{%family_member}}';
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
            [['family_id', 'user_id', 'role'], 'required'],
            ['role', 'in', 'range' => self::ROLES],
            ['user_id', 'unique', 'message' => 'This user already belongs to a family.'],
            ['family_id', 'exist', 'targetClass' => Family::class, 'targetAttribute' => 'id'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
        ];
    }

    /**
     * Every parent also plays: create their game profile on joining a family.
     *
     * @param array<string, mixed> $changedAttributes
     */
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert && !ChildProfile::find()->where(['user_id' => $this->user_id])->exists()) {
            $user = User::findOne($this->user_id);
            $player = new ChildProfile([
                'name' => mb_substr((string) ($user->name ?? $user->username ?? 'Genitore'), 0, 40),
                'avatar' => 'owl',
                'color' => '#6B6B1F',
                'sort_order' => 1000,
            ]);
            $player->family_id = $this->family_id;
            $player->user_id = $this->user_id;
            $player->save(false);
        }
    }

    public function getFamily(): ActiveQuery
    {
        return $this->hasOne(Family::class, ['id' => 'family_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id',
            'user_id',
            'username' => fn (self $m): string => $m->user->username,
            'name' => fn (self $m): ?string => $m->user->name,
            'role',
        ];
    }
}
