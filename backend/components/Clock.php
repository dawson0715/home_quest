<?php

declare(strict_types=1);

namespace app\components;

use yii\base\Component;

/**
 * Current time (application component `clock`), Europe/Rome. Tests freeze it with setNow().
 */
class Clock extends Component
{
    private ?\DateTimeImmutable $frozen = null;

    public function now(): \DateTimeImmutable
    {
        return $this->frozen ?? new \DateTimeImmutable('now', new \DateTimeZone(\Yii::$app->timeZone));
    }

    /** DATETIME string for the DB (local time, Europe/Rome). */
    public function nowDb(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    public function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    public function setNow(?\DateTimeImmutable $now): void
    {
        $this->frozen = $now;
    }
}
