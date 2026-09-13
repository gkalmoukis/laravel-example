<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TransactionType;

/**
 * One category's twelve months of Plan, Actual and Forecast (§7.1).
 *
 * Subcategory amounts are already rolled up into their parent here: a category is the unit
 * every report shows, and its breakdown is fetched separately when a row is expanded
 * (CAT-08).
 */
final readonly class CategoryFigures
{
    /**
     * @param  array<int, int>  $plan  month (1-12) to cents
     * @param  array<int, int>  $actual
     * @param  array<int, int>  $forecast
     */
    public function __construct(
        public int $categoryId,
        public string $categoryName,
        public TransactionType $type,
        public array $plan,
        public array $actual,
        public array $forecast,
    ) {}

    public function plannedFor(int $month): int
    {
        return $this->plan[$month] ?? 0;
    }

    public function actualFor(int $month): int
    {
        return $this->actual[$month] ?? 0;
    }

    public function forecastFor(int $month): int
    {
        return $this->forecast[$month] ?? 0;
    }

    public function plannedYear(): int
    {
        return array_sum($this->plan);
    }

    public function actualYear(): int
    {
        return array_sum($this->actual);
    }
}
