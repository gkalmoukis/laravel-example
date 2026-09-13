<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\GoalType;
use Carbon\CarbonImmutable;

/**
 * How a goal is doing (§7.7).
 *
 * Where the current amount comes from depends on the goal: the emergency fund and the
 * year-end balance are both derived from figures the application already knows, so asking
 * the user to keep them up to date would be asking them to copy numbers between screens.
 * Everything else is theirs to maintain (GOAL-03).
 */
final readonly class GoalProgress
{
    public function __construct(
        public int $goalId,
        public string $name,
        public GoalType $type,
        public int $targetCents,
        public int $currentCents,
        public int $remainingCents,
        public int $monthlyContributionCents,
        public ?CarbonImmutable $targetDate,
        public ?CarbonImmutable $estimatedCompletion,
        public bool $isReached,
        public bool $isOffTrack,
        /** Whether the current amount is derived rather than entered (GOAL-03). */
        public bool $isTracked,
    ) {}

    /**
     * Progress as a pair rather than a percentage: full precision, rounded only for
     * display (§7).
     *
     * @return array{0: int, 1: int}
     */
    public function progress(): array
    {
        return [min($this->currentCents, $this->targetCents), $this->targetCents];
    }
}
