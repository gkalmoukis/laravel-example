<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A year in figures (§7.3).
 *
 * "Actual so far" and "actual over completed months" are deliberately different numbers.
 * The dashboard wants everything recorded to date, however provisional; Plan vs Actual
 * wants only months the user has signed off, compared against the plan for those same
 * months, or it would measure a part-month against a whole one (CMP-04).
 *
 * Savings rates are not computed here. The rule is to work at full precision and round
 * only for display, so the figures cross the wire as cents and the interface divides.
 */
final readonly class AnnualSummary
{
    public function __construct(
        public int $plannedIncomeCents,
        public int $plannedExpenseCents,
        public int $actualSoFarIncomeCents,
        public int $actualSoFarExpenseCents,
        public int $completedIncomeCents,
        public int $completedExpenseCents,
        public int $completedPlannedIncomeCents,
        public int $completedPlannedExpenseCents,
        public int $completedMonths,
        public int $forecastIncomeCents,
        public int $forecastExpenseCents,
        public int $plannedYearEndCents,
        public int $forecastYearEndCents,
        public int $deviationCents,
        public bool $hasBaseline,
    ) {}

    public function plannedSavingsCents(): int
    {
        return $this->plannedIncomeCents - $this->plannedExpenseCents;
    }

    public function actualSoFarSavingsCents(): int
    {
        return $this->actualSoFarIncomeCents - $this->actualSoFarExpenseCents;
    }

    public function forecastSavingsCents(): int
    {
        return $this->forecastIncomeCents - $this->forecastExpenseCents;
    }
}
