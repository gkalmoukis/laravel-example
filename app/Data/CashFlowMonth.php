<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MonthStatus;

/**
 * A month of the twelve-month cash-flow table (CF-01).
 *
 * The actual line is missing rather than zero for months the user has not reached yet: a
 * zero would read as "earned and spent nothing", which is a claim about the future the
 * application is in no position to make.
 */
final readonly class CashFlowMonth
{
    public function __construct(
        public int $month,
        public MonthStatus $status,
        public BalanceLine $planned,
        public BalanceLine $forecast,
        public ?BalanceLine $actual,
    ) {}
}
