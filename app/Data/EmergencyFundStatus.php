<?php

declare(strict_types=1);

namespace App\Data;

/**
 * How far along the emergency fund is (§7.6).
 *
 * The target is money the user would need if their income stopped, so it is derived from
 * what they must keep paying — not from what they happen to spend. A custom target
 * overrides that, because someone with an irregular income may know better than the sum.
 */
final readonly class EmergencyFundStatus
{
    public function __construct(
        public int $essentialMonthlyCents,
        public int $monthsOfCover,
        public int $targetCents,
        public bool $targetIsCustom,
        public int $currentCents,
        public int $remainingCents,
        public int $monthlyContributionCents,
        /** Months from now until the target is met, or null when it never is. */
        public ?int $monthsToTarget,
        public bool $isReached,
        public int $projectedYearEndCents,
        /** Whether the user has recorded anything set aside at all (EF-03). */
        public bool $hasBeenStarted,
    ) {}

    /**
     * Progress as a share of the target, at full precision.
     *
     * Returned as a pair of integers rather than a percentage: the rule is to compute at
     * full precision and round only for display (§7).
     *
     * @return array{0: int, 1: int}
     */
    public function progress(): array
    {
        return [min($this->currentCents, $this->targetCents), $this->targetCents];
    }
}
