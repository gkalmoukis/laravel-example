<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;

/**
 * The opening position as the plan screens show it (OPEN-01, OPEN-03).
 *
 * The setup wizard and the plan's own opening tab render the same form, and each
 * controller had built these props for itself in near-identical code. One of them was
 * always going to drift.
 */
final readonly class PresentOpeningPosition
{
    public function __construct(private UpdateOpeningPosition $openingPosition) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(FinancialYear $year): array
    {
        $snapshots = $year->netWorthSnapshots()
            ->with('netWorthItem')
            ->where('month', NetWorthSnapshot::OPENING_MONTH)
            ->get();

        return [
            'holdings' => $snapshots
                ->filter(fn (NetWorthSnapshot $snapshot): bool => $snapshot->netWorthItem->is_active)
                ->sortBy(fn (NetWorthSnapshot $snapshot): int => $snapshot->netWorthItem->sort_order)
                ->map(fn (NetWorthSnapshot $snapshot): array => [
                    'id' => $snapshot->netWorthItem->id,
                    'name' => $snapshot->netWorthItem->name,
                    'kind' => $snapshot->netWorthItem->kind->value,
                    'valueCents' => $snapshot->value_cents->cents,
                ])
                ->values()
                ->all(),
            // Shown live as the user types (OPEN-03).
            'openingLiquidCents' => $this->openingPosition->openingLiquidBalance($year)->cents,
            'openingNetWorthCents' => $this->openingPosition->openingNetWorth($year),
        ];
    }
}
