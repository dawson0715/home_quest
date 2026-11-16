<?php

declare(strict_types=1);

namespace app\components;

use yii\base\Event;
use yii\web\Response;

/**
 * Security headers on every response, and a safety net for error responses that
 * bypassed both the Serializer and the ApiErrorHandler: wraps them in the
 * {data, meta.errors[{code, message}]} envelope.
 */
final class ResponseEnvelope
{
    /** Security headers for every API response (JSON only, never framed or sniffed). */
    public const SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        'Cache-Control' => 'no-store',
    ];

    public static function onBeforeSend(Event $event): void
    {
        /** @var Response $response */
        $response = $event->sender;
        foreach (self::SECURITY_HEADERS as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        if ($response->statusCode < 400 || $response->format !== Response::FORMAT_JSON) {
            return;
        }

        $data = $response->data;
        if (is_array($data) && array_key_exists('meta', $data)) {
            return;
        }

        $message = is_array($data) ? (string) ($data['message'] ?? 'Error') : (string) $data;
        $response->data = [
            'data' => null,
            'meta' => [
                'errors' => [
                    ['code' => ErrorCode::fromStatus($response->statusCode), 'message' => $message],
                ],
            ],
        ];
    }
}
