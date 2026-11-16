<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\Clock;
use app\components\ErrorCode;
use app\components\TokenHasher;
use app\models\ChildProfile;
use app\models\ChildSession;
use app\models\Device;
use app\models\FamilyMember;
use app\models\PairingCode;
use Yii;
use yii\caching\CacheInterface;
use yii\web\NotFoundHttpException;

/**
 * Device pairing and child sessions (docs/02-architettura.md §5).
 */
class DeviceService
{
    public const PIN_MAX_FAILURES = 5;
    public const PAIR_MAX_ATTEMPTS = 10;
    public const WINDOW_SECONDS = 600;

    public function __construct(
        private readonly Clock $clock,
        private readonly CacheInterface $cache,
    ) {
    }

    public static function create(): self
    {
        return new self(Yii::$app->get('clock'), Yii::$app->cache);
    }

    public function createPairingCode(FamilyMember $creator): PairingCode
    {
        $now = $this->clock->now();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = PairingCode::randomCode();
            // codes are globally unique: free an expired/used row holding the same code
            PairingCode::deleteAll(['and', ['code' => $code], ['or', ['not', ['used_at' => null]], ['<', 'expires_at', $now->format('Y-m-d H:i:s')]]]);
            if (PairingCode::find()->where(['code' => $code])->exists()) {
                continue;
            }
            $pairing = new PairingCode();
            $pairing->family_id = $creator->family_id;
            $pairing->code = $code;
            $pairing->created_by = $creator->user_id;
            $pairing->expires_at = $now->modify('+' . PairingCode::TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            $pairing->save(false);
            return $pairing;
        }
        throw new \RuntimeException('Could not generate a unique pairing code.');
    }

    /**
     * @return array{device: Device, token: string}
     */
    public function pair(string $code, string $deviceName, string $clientKey): array
    {
        $this->hit("pair:$clientKey", self::PAIR_MAX_ATTEMPTS);

        $device = new Device(['name' => $deviceName]);
        if (!$device->validate(['name'])) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, (string) $device->getFirstError('name'), 422, 'device_name');
        }

        $tx = Device::getDb()->beginTransaction();
        try {
            $pairing = PairingCode::findBySql(
                'SELECT * FROM {{%pairing_code}} WHERE code = :code AND used_at IS NULL AND expires_at > :now FOR UPDATE',
                [':code' => PairingCode::normalize($code), ':now' => $this->clock->nowDb()]
            )->one();
            if (!$pairing instanceof PairingCode) {
                throw new ApiException(ErrorCode::PAIRING_CODE_INVALID, 'Invalid or expired pairing code.', 422, 'code');
            }
            $pairing->updateAttributes(['used_at' => $this->clock->nowDb()]);

            $token = TokenHasher::generate(TokenHasher::DEVICE_PREFIX);
            $device->family_id = $pairing->family_id;
            $device->token_hash = TokenHasher::hash($token);
            $device->save(false);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $this->cache->delete("pair:$clientKey");
        return ['device' => $device, 'token' => $token];
    }

    /**
     * @return list<ChildProfile>
     */
    public function profiles(Device $device): array
    {
        return ChildProfile::find()
            ->forFamily($device->family_id)
            ->andWhere(['archived_at' => null])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * @return array{session: ChildSession, token: string}
     */
    public function selectChild(Device $device, int $childId, ?string $pin): array
    {
        $child = ChildProfile::find()
            ->forFamily($device->family_id)
            ->andWhere(['id' => $childId, 'archived_at' => null])
            ->one();
        if (!$child instanceof ChildProfile) {
            throw new NotFoundHttpException("Object not found: $childId");
        }

        if ($child->hasPin()) {
            $key = "pin:{$device->id}:{$child->id}";
            $failures = (int) $this->cache->get($key);
            if ($failures >= self::PIN_MAX_FAILURES) {
                throw new ApiException(ErrorCode::RATE_LIMITED, 'Too many wrong PINs, try again later.', 429);
            }
            if ($pin === null || !$child->validatePin($pin)) {
                $this->cache->set($key, $failures + 1, self::WINDOW_SECONDS);
                throw new ApiException(ErrorCode::PIN_INVALID, 'Wrong PIN.', 422, 'pin');
            }
            $this->cache->delete($key);
        }

        $token = TokenHasher::generate(TokenHasher::CHILD_PREFIX);
        $session = new ChildSession();
        $session->family_id = $device->family_id;
        $session->device_id = $device->id;
        $session->child_id = $child->id;
        $session->token_hash = TokenHasher::hash($token);
        $session->save(false);
        $session->populateRelation('child', $child);

        return ['session' => $session, 'token' => $token];
    }

    public function revoke(Device $device): void
    {
        if (!$device->isRevoked()) {
            $device->updateAttributes(['revoked_at' => $this->clock->nowDb()]);
        }
        ChildSession::deleteAll(['device_id' => $device->id]);
    }

    /** Counts an attempt; 429 when the limit is reached within the window. */
    private function hit(string $key, int $max): void
    {
        $count = (int) $this->cache->get($key);
        if ($count >= $max) {
            throw new ApiException(ErrorCode::RATE_LIMITED, 'Too many attempts, try again later.', 429);
        }
        $this->cache->set($key, $count + 1, self::WINDOW_SECONDS);
    }
}
