<?php

declare(strict_types=1);

namespace tests\_support;

use app\components\AiClient;
use app\components\AiException;

/**
 * Test double: records the requests and returns a canned answer
 * (or a callable building it from the request), or fails.
 */
final class FakeAiClient implements AiClient
{
    /** @var list<array{system: string, input: string, schema: array<string, mixed>}> */
    public array $requests = [];

    /**
     * @param array<mixed>|\Closure(array<string, mixed>): array<mixed>|null $answer null = throw AiException
     */
    public function __construct(public array|\Closure|null $answer = null, public bool $enabled = true)
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function completeJson(string $system, string $input, array $schema): array
    {
        $this->requests[] = ['system' => $system, 'input' => $input, 'schema' => $schema];
        if ($this->answer === null) {
            throw new AiException('fake failure');
        }
        if ($this->answer instanceof \Closure) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($input, true);
            return ($this->answer)($decoded);
        }
        return $this->answer;
    }
}
