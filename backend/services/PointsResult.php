<?php

declare(strict_types=1);

namespace app\services;

use app\models\PointTransaction;

final class PointsResult
{
    public function __construct(
        public readonly PointTransaction $transaction,
        public readonly int $balance,
        public readonly int $xpTotal,
        public readonly int $level,
        public readonly bool $levelUp,
    ) {
    }
}
