<?php

declare(strict_types=1);

namespace tests\_support;

use app\components\PushSender;

/**
 * Test double: records pushes; endpoints listed in $expired report "gone".
 */
final class RecordingPushSender implements PushSender
{
    /** @var list<array{endpoint: string, payload: array<string, mixed>}> */
    public array $sent = [];
    /** @var list<string> */
    public array $expired = [];

    public function isEnabled(): bool
    {
        return true;
    }

    public function send(array $subscription, array $payload): bool
    {
        $this->sent[] = ['endpoint' => $subscription['endpoint'], 'payload' => $payload];
        return !in_array($subscription['endpoint'], $this->expired, true);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_map(fn (array $s): string => (string) $s['payload']['type'], $this->sent);
    }
}
