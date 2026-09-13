<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A year's balances, month by month (§7.2).
 *
 * Each month's closing balance is the next month's opening balance, so the three series
 * are running totals over the opening position rather than independent figures.
 */
final readonly class CashFlow
{
    /**
     * @param  array<int, CashFlowMonth>  $months  month (1-12) to its three lines
     */
    public function __construct(
        public int $openingBalanceCents,
        public array $months,
        public ?int $lastActualMonth,
        public int $currentAvailableCents,
    ) {}

    public function month(int $month): CashFlowMonth
    {
        return $this->months[$month];
    }

    public function plannedYearEndCents(): int
    {
        return $this->months[12]->planned->closingCents;
    }

    public function forecastYearEndCents(): int
    {
        return $this->months[12]->forecast->closingCents;
    }
}
