<?php

declare(strict_types=1);

namespace app\components;

use Yii;

/**
 * Google Gemini through the Interactions API (POST /v1beta/interactions),
 * with structured JSON output. Paid tier only: the free tier may not be used
 * for apps serving EU users.
 */
class GeminiClient implements AiClient
{
    public const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';
    public const API_REVISION = '2026-05-20';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 40,
    ) {
    }

    public static function fromParams(): self
    {
        /** @var array{apiKey?: string|null, model?: string, timeout?: int} $params */
        $params = Yii::$app->params['gemini'];
        return new self($params['apiKey'] ?? null, $params['model'] ?? 'gemini-3.5-flash-lite', (int) ($params['timeout'] ?? 40));
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function completeJson(string $system, string $input, array $schema): array
    {
        if (!$this->isEnabled()) {
            throw new AiException('Gemini is not configured.');
        }
        $body = [
            'model' => $this->model,
            'system_instruction' => $system,
            'input' => $input,
            'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema],
            'generation_config' => ['thinking_level' => 'low', 'max_output_tokens' => 8192],
            // do not keep the interaction on Google's side
            'store' => false,
        ];
        [$status, $raw] = $this->post((string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $response = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($response) ? (string) ($response['error']['message'] ?? '') : '';
            throw new AiException("Gemini HTTP $status" . ($message === '' ? '' : ': ' . mb_substr($message, 0, 200)));
        }
        if (!is_array($response)) {
            throw new AiException('Gemini: response is not JSON.');
        }
        return self::decodeAnswer($response);
    }

    /**
     * Extracts and decodes the JSON text of a completed interaction.
     *
     * @param array<mixed> $response
     * @return array<mixed>
     */
    public static function decodeAnswer(array $response): array
    {
        $status = $response['status'] ?? null;
        if ($status !== 'completed') {
            throw new AiException('Gemini: interaction ' . (is_string($status) ? $status : 'without status') . '.');
        }
        $text = '';
        foreach ((array) ($response['steps'] ?? []) as $step) {
            if (!is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                continue;
            }
            foreach ((array) ($step['content'] ?? []) as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }
        if ($text === '' && is_string($response['output_text'] ?? null)) {
            $text = $response['output_text'];
        }
        $answer = json_decode(trim($text), true);
        if (!is_array($answer)) {
            throw new AiException('Gemini: the answer is not valid JSON.');
        }
        return $answer;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function post(string $json): array
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Api-Revision: ' . self::API_REVISION,
                'x-goog-api-key: ' . $this->apiKey,
            ],
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if (!is_string($raw)) {
            throw new AiException('Gemini: ' . ($error !== '' ? $error : 'request failed') . '.');
        }
        return [$status, $raw];
    }
}
