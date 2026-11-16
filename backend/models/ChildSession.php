<?php

declare(strict_types=1);

namespace app\models;

use app\components\TokenHasher;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\web\IdentityInterface;

/**
 * A child acting on a paired device after choosing the profile (and PIN).
 * Invalid as soon as the device is revoked or the child archived.
 *
 * @property int $id
 * @property int $family_id
 * @property int $device_id
 * @property int $child_id
 * @property string $token_hash
 * @property string|null $last_seen_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Device $device
 * @property-read ChildProfile $child
 */
class ChildSession extends FamilyScopedRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%child_session}}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function getDevice(): ActiveQuery
    {
        return $this->hasOne(Device::class, ['id' => 'device_id']);
    }

    public function getChild(): ActiveQuery
    {
        return $this->hasOne(ChildProfile::class, ['id' => 'child_id']);
    }

    public static function findActiveByToken(string $token): ?self
    {
        return static::find()
            ->alias('s')
            ->innerJoinWith(['device d', 'child c'])
            ->where(['s.token_hash' => TokenHasher::hash($token), 'd.revoked_at' => null, 'c.archived_at' => null])
            ->one();
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
        return 'child-session:' . $this->id;
    }

    public function getAuthKey(): ?string
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        throw new NotSupportedException('Child sessions use bearer tokens only.');
    }

    /** Updates last_seen_at (session and device) at most every 5 minutes. */
    public function touchLastSeen(): void
    {
        $now = Yii::$app->get('clock')->now();
        if ($this->last_seen_at === null || strtotime($this->last_seen_at) < $now->getTimestamp() - 300) {
            $this->updateAttributes(['last_seen_at' => $now->format('Y-m-d H:i:s')]);
        }
        $this->device->touchLastSeen();
    }
}
