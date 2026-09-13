<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TransactionType;

/**
 * A financial year's figures, per category and per month (§7.1).
 *
 * Nothing here is stored. Every actual, variance, balance and forecast in the application
 * is read back out of transactions and plan amounts on each request, so a corrected
 * transaction changes every figure it touches at once (UX-02, UX-03).
 */
final readonly class MonthlyFigures
{
    /**
     * @param  list<CategoryFigures>  $categories
     * @param  array<int, MonthTotals>  $months  month (1-12) to its totals
     */
    public function __construct(
        public int $year,
        public array $categories,
        public array $months,
    ) {}

    public function month(int $month): MonthTotals
    {
        return $this->months[$month];
    }

    /**
     * @return list<CategoryFigures>
     */
    public function categoriesOfType(TransactionType $type): array
    {
        return array_values(array_filter(
            $this->categories,
            fn (CategoryFigures $figures): bool => $figures->type === $type,
        ));
    }
}
