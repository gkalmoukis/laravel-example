<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TransactionType;
use App\Enums\VarianceStatus;

/**
 * How one category did against its plan for a period (§7.4).
 *
 * The variance is signed — over for an expense, short for an income — and is not turned
 * into a percentage here: the rule is to work at full precision and round only for
 * display, and a plan of zero has no percentage at all.
 */
final readonly class Variance
{
    public function __construct(
        public int $categoryId,
        public string $categoryName,
        public TransactionType $type,
        public int $plannedCents,
        public int $actualCents,
        public int $varianceCents,
        public VarianceStatus $status,
        public bool $isSpread,
    ) {}

    public function label(): string
    {
        return $this->status->label($this->type);
    }
}
