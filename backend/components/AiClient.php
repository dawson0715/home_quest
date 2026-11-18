<?php

declare(strict_types=1);

namespace app\components;

/**
 * A language model that answers with JSON matching a schema.
 * Callers send only anonymous data (no names, ids or family details).
 */
interface AiClient
{
    /** False when no provider is configured (no API key). */
    public function isEnabled(): bool;

    /**
     * @param array<string, mixed> $schema JSON schema of the expected answer
     * @return array<mixed> the decoded JSON answer
     * @throws AiException on transport, provider or decoding errors
     */
    public function completeJson(string $system, string $input, array $schema): array;
}
