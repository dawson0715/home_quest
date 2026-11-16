<?php

declare(strict_types=1);

namespace app\controllers;

trait EnvelopeMetaTrait
{
    /** @var array<string, mixed> */
    public array $responseMeta = [];

    public function addResponseMeta(string $key, mixed $value): void
    {
        $this->responseMeta[$key] = $value;
    }
}
