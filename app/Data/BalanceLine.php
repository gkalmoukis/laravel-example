<?php

declare(strict_types=1);

namespace App\Data;

/**
 * One month of one series: what it opened with, what moved, what it closed with (§7.2).
 *
 * Every figure here is a signed integer rather than Money. A month can spend more than it
 * earns and a balance can go below zero — that is the forecast's most important warning,
 * not an impossible value (recorded in .ai/rules/foundation-decisions.md).
 */
final readonly class BalanceLine
{
    public function __construct(
        public int $openingCents,
        public int $incomeCents,
        public int $expenseCents,
        public int $netCents,
        public int $closingCents,
    ) {}
}
