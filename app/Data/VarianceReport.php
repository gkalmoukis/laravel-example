<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Plan against actual, category by category (CMP-03, CMP-04).
 *
 * Income and expenses are kept apart because they are read differently: one is a ceiling,
 * the other a target, and mixing them in one ranked list would put a good month's overtime
 * next to an overspent grocery bill.
 */
final readonly class VarianceReport
{
    /**
     * @param  list<Variance>  $income
     * @param  list<Variance>  $expenses
     */
    public function __construct(
        public ?int $month,
        public int $completedMonths,
        public array $income,
        public array $expenses,
    ) {}

    public function isYearToDate(): bool
    {
        return $this->month === null;
    }
}
