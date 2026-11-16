<?php

declare(strict_types=1);

namespace app\models;

use app\components\TokenHasher;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\web\IdentityInterface;

/**
 * A device paired to a family (e.g. the kids' tablet). Its token only allows
 * listing profiles and starting a child session.
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property string $token_hash
 * @property string|null $last_seen_at
 * @property string|null $revoked_at
 * @property int $created_at
 * @property int $updated_at
 */
class Device extends FamilyScopedRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%device}}';
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
            ['name', 'string', 'max' => 60],
        ];
    }

    public static function findActiveByToken(string $token): ?self
    {
        return static::find()->where(['token_hash' => TokenHasher::hash($token), 'revoked_at' => null])->one();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id',
            'name',
            'last_seen_at',
            'created_at' => fn (self $d): string => date(DATE_ATOM, (int) $d->created_at),
            'revoked' => fn (self $d): bool => $d->isRevoked(),
        ];
    }

    public static function findIdentity($id): ?IdentityInterface
    {
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null): ?IdentityInterface
    {
        return is_string($token) ? static::findActiveByToken($token) : null;
    }

    public function getId(): string
    {
        return 'device:' . $this->id;
    }

    public function getAuthKey(): ?string
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        throw new NotSupportedException('Devices use bearer tokens only.');
    }

    /** Updates last_seen_at at most every 5 minutes. */
    public function touchLastSeen(): void
    {
        $now = Yii::$app->get('clock')->now();
        if ($this->last_seen_at === null || strtotime($this->last_seen_at) < $now->getTimestamp() - 300) {
            $this->updateAttributes(['last_seen_at' => $now->format('Y-m-d H:i:s')]);
        }
    }
}
