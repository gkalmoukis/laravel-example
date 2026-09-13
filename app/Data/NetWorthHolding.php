<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NetWorthItemKind;

/**
 * What one holding was worth in one month (§7.8).
 *
 * A value the user never gave for this month is carried forward from the last one they
 * did, and says so: an investment they valued in March is presumably still worth roughly
 * that in April, but presenting a stale figure as fresh would overstate how much the
 * application actually knows (NW-04).
 */
final readonly class NetWorthHolding
{
    public function __construct(
        public int $itemId,
        public string $name,
        public NetWorthItemKind $kind,
        public int $valueCents,
        public bool $isCarriedForward,
    ) {}
}
