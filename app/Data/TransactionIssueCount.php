<?php

declare(strict_types=1);

namespace App\Data;

/**
 * How many transactions cannot be counted as they stand (TXV-05, ALRT-01, ALRT-02).
 *
 * Counted once and shared, so the dashboard's figure and the alert's sentence can never
 * disagree about how many there are.
 */
final readonly class TransactionIssueCount
{
    public function __construct(
        public int $total,
        public int $categoryTypeMismatch,
    ) {}

    public function any(): bool
    {
        return $this->total > 0;
    }
}
