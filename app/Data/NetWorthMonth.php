<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Everything owned and owed at one point in the year (§7.8).
 *
 * Debts are held as what is still owed — a positive number — and subtracted here, so a
 * debt is never a negative asset and the figures the user typed read back the way they
 * typed them.
 */
final readonly class NetWorthMonth
{
    /**
     * @param  list<NetWorthHolding>  $holdings
     * @param  array<string, int>  $byKind  kind value to total cents
     */
    public function __construct(
        public int $month,
        public int $assetsCents,
        public int $debtsCents,
        public array $byKind,
        public array $holdings,
    ) {}

    /**
     * Net worth can be negative — owing more than you own is a real position, and hiding
     * it would be the one thing this figure must never do.
     */
    public function netCents(): int
    {
        return $this->assetsCents - $this->debtsCents;
    }
}
