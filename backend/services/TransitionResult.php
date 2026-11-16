<?php

declare(strict_types=1);

namespace app\services;

use app\models\TaskOccurrence;

/**
 * Outcome of submit/approve: what the child should be celebrated for.
 */
final class TransitionResult
{
    public ?TaskOccurrence $occurrence = null;
    public int $pointsAwarded = 0;
    public bool $levelUp = false;
    public ?int $balance = null;
    /** @var list<array{code: string, icon: string}> badges just earned */
    public array $newBadges = [];
    /** set when the parents must be told (task waiting for approval) */
    public bool $notifyParents = false;

    /**
     * @return array{occurrence: TaskOccurrence|null, points_awarded: int, level_up: bool, balance: int|null, new_badges: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'occurrence' => $this->occurrence,
            'points_awarded' => $this->pointsAwarded,
            'level_up' => $this->levelUp,
            'balance' => $this->balance,
            'new_badges' => $this->newBadges,
        ];
    }
}
