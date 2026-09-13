<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\NetWorthHolding;
use App\Data\NetWorthMonth;
use App\Data\NetWorthPosition;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;

/**
 * Everything owned minus everything owed, month by month (§7.8).
 *
 * Unlike the rest of the application's figures, this one cannot be derived from
 * transactions: only the user can say what an investment is now worth or what is left on
 * a loan. So the answer is built from the values they have given, and a month they
 * skipped carries the last value forward rather than dropping to zero — which would show
 * a house vanishing and reappearing.
 */
final readonly class CalculateNetWorth
{
    private const int MONTHS = 12;

    public function handle(FinancialYear $financialYear): NetWorthPosition
    {
        $items = $this->activeItems($financialYear);
        $recorded = $this->recordedValues($financialYear);

        $months = [];
        $carried = [];
        $latestRecorded = null;

        for ($month = NetWorthSnapshot::OPENING_MONTH; $month <= self::MONTHS; $month++) {
            $holdings = [];

            foreach ($items as $item) {
                $given = $recorded[$item->id][$month] ?? null;

                if ($given !== null) {
                    $carried[$item->id] = $given;
                }

                $holdings[] = new NetWorthHolding(
                    itemId: $item->id,
                    name: $item->name,
                    kind: $item->kind,
                    valueCents: $carried[$item->id] ?? 0,
                    // Only a value the user gave for some earlier month is carried; a
                    // holding they have never valued shows as nothing, not as stale.
                    isCarriedForward: $given === null && isset($carried[$item->id]),
                );
            }

            if ($this->anyRecordedIn($recorded, $month)) {
                $latestRecorded = $month;
            }

            $months[$month] = $this->position($month, $holdings);
        }

        return new NetWorthPosition(
            year: $financialYear->year,
            months: $months,
            latestRecordedMonth: $latestRecorded,
        );
    }

    /**
     * @param  list<NetWorthHolding>  $holdings
     */
    private function position(int $month, array $holdings): NetWorthMonth
    {
        $assets = 0;
        $debts = 0;
        $byKind = [];

        foreach (NetWorthItemKind::cases() as $kind) {
            $byKind[$kind->value] = 0;
        }

        foreach ($holdings as $holding) {
            $byKind[$holding->kind->value] += $holding->valueCents;

            if ($holding->kind === NetWorthItemKind::Debt) {
                $debts += $holding->valueCents;

                continue;
            }

            $assets += $holding->valueCents;
        }

        return new NetWorthMonth(
            month: $month,
            assetsCents: $assets,
            debtsCents: $debts,
            byKind: $byKind,
            holdings: $holdings,
        );
    }

    /**
     * Retired holdings are left out entirely: they are no longer part of what the user
     * has, and carrying their last value forever would keep counting something gone
     * (NW-02).
     *
     * @return list<NetWorthItem>
     */
    private function activeItems(FinancialYear $financialYear): array
    {
        $items = [];

        $records = $financialYear->user->netWorthItems()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($records as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Every value the user has given for this year, read in one query.
     *
     * @return array<int, array<int, int>> item id to month to cents
     */
    private function recordedValues(FinancialYear $financialYear): array
    {
        $snapshots = NetWorthSnapshot::query()
            ->where('financial_year_id', $financialYear->id)
            ->get();

        $values = [];

        foreach ($snapshots as $snapshot) {
            $values[$snapshot->net_worth_item_id][$snapshot->month] = $snapshot->value_cents->cents;
        }

        return $values;
    }

    /**
     * @param  array<int, array<int, int>>  $recorded
     */
    private function anyRecordedIn(array $recorded, int $month): bool
    {
        return array_any($recorded, fn (array $byMonth): bool => isset($byMonth[$month]));
    }
}
