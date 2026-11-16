<?php

declare(strict_types=1);

namespace app\components;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Yii;

/**
 * VAPID web push (minishlink/web-push). Without keys (params['vapid']) it is
 * disabled and sends nothing.
 */
class WebPushSender implements PushSender
{
    private ?WebPush $client = null;

    /**
     * @param array{subject?: string, publicKey?: string, privateKey?: string} $vapid
     */
    public function __construct(private readonly array $vapid)
    {
    }

    public static function fromParams(): self
    {
        /** @var array{subject?: string, publicKey?: string, privateKey?: string} $vapid */
        $vapid = Yii::$app->params['vapid'];
        return new self($vapid);
    }

    public function isEnabled(): bool
    {
        return !empty($this->vapid['publicKey']) && !empty($this->vapid['privateKey']);
    }

    public function publicKey(): ?string
    {
        return $this->isEnabled() ? (string) $this->vapid['publicKey'] : null;
    }

    public function send(array $subscription, array $payload): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }
        $this->client ??= new WebPush(['VAPID' => [
            'subject' => $this->vapid['subject'] ?? 'mailto:admin@example.invalid',
            'publicKey' => $this->vapid['publicKey'],
            'privateKey' => $this->vapid['privateKey'],
        ]], ['TTL' => 3600, 'urgency' => 'normal']);

        $report = $this->client->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'keys' => ['p256dh' => $subscription['p256dh'], 'auth' => $subscription['auth']],
            ]),
            (string) json_encode($payload)
        );
        if (!$report->isSuccess()) {
            Yii::warning('Push failed: ' . $report->getReason(), __METHOD__);
        }
        return !$report->isSubscriptionExpired();
    }
}
