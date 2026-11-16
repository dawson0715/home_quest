<?php

declare(strict_types=1);

namespace app\components;

use Yii;

/**
 * Long-lived opaque tokens for devices and child sessions: only the SHA-256 is stored.
 * Prefixes route the lookup: d_ = device, c_ = child session (user tokens have none).
 */
final class TokenHasher
{
    public const DEVICE_PREFIX = 'd_';
    public const CHILD_PREFIX = 'c_';

    public static function generate(string $prefix): string
    {
        return $prefix . Yii::$app->security->generateRandomString(43);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
