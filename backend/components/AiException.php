<?php

declare(strict_types=1);

namespace app\components;

/**
 * The AI provider failed or answered with something unusable.
 * Messages never contain the request or response payload.
 */
class AiException extends \RuntimeException
{
}
