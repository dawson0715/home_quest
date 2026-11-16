<?php

declare(strict_types=1);

namespace app\components;

/**
 * Sends one web push message. Returns false when the subscription is gone
 * (404/410) and must be deleted.
 */
interface PushSender
{
    /**
     * @param array{endpoint: string, p256dh: string, auth: string} $subscription
     * @param array<string, mixed> $payload
     */
    public function send(array $subscription, array $payload): bool;

    public function isEnabled(): bool;
}
