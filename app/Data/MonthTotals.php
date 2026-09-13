<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MonthStatus;

/**
 * What a single month adds up to across every category (§7.1).
 *
 * Net is income minus expenses and is a derived figure, so it is a signed integer rather
 * than Money: a month that spent more than it earned is a real answer, not an error.
 */
final readonly class MonthTotals
{
    public function __construct(
        public int $month,
        public MonthStatus $status,
        public int $plannedIncomeCents,
        public int $plannedExpenseCents,
        public int $actualIncomeCents,
        public int $actualExpenseCents,
        public int $forecastIncomeCents,
        public int $forecastExpenseCents,
    ) {}

    public function plannedNetCents(): int
    {
        return $this->plannedIncomeCents - $this->plannedExpenseCents;
    }

    public function actualNetCents(): int
    {
        return $this->actualIncomeCents - $this->actualExpenseCents;
    }

    public function forecastNetCents(): int
    {
        return $this->forecastIncomeCents - $this->forecastExpenseCents;
    }
}
