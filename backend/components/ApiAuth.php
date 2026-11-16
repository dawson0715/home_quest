<?php

declare(strict_types=1);

namespace app\components;

use app\models\ChildSession;
use app\models\Device;
use app\models\User;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\web\IdentityInterface;

/**
 * Bearer auth for the three kinds of actor: parent users (legacy plain token),
 * paired devices (d_…) and child sessions (c_…). See docs/02-architettura.md §5.
 */
class ApiAuth extends HttpBearerAuth
{
    public const MAX_FAILURES = 20;
    public const FAILURE_WINDOW = 600;

    public function authenticate($user, $request, $response): ?IdentityInterface
    {
        $header = $request->getHeaders()->get($this->header);
        if (!is_string($header) || !preg_match($this->pattern, $header, $matches)) {
            return null;
        }

        // brute force guard: too many invalid tokens from one IP → 429
        $key = 'auth-fail:' . $request->getUserIP();
        $cache = Yii::$app->cache;
        if ((int) $cache->get($key) >= self::MAX_FAILURES) {
            throw new ApiException(ErrorCode::RATE_LIMITED, 'Too many invalid tokens, try again later.', 429);
        }
        $identity = self::resolve(trim($matches[1]));
        if ($identity === null) {
            $cache->set($key, (int) $cache->get($key) + 1, self::FAILURE_WINDOW);
            $this->challenge($response);
            $this->handleFailure($response);
        }

        $user->switchIdentity($identity);
        if ($identity instanceof Device || $identity instanceof ChildSession) {
            $identity->touchLastSeen();
        }

        return $identity;
    }

    public static function resolve(string $token): ?IdentityInterface
    {
        if ($token === '') {
            return null;
        }
        $identity = match (true) {
            str_starts_with($token, TokenHasher::DEVICE_PREFIX) => Device::findActiveByToken($token),
            str_starts_with($token, TokenHasher::CHILD_PREFIX) => ChildSession::findActiveByToken($token),
            default => null,
        };
        // Legacy user tokens are random and may start with a prefix by chance
        return $identity ?? User::findIdentityByAccessToken($token);
    }
}
