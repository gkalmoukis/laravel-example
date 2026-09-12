<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records where the year starts: what each holding was worth before January (OPEN-01).
 *
 * Everything else in the year is measured from here — the opening liquid balance is what
 * each month's cash flow is added to.
 */
final readonly class UpdateOpeningPosition
{
    /**
     * @param  array<int, Money>  $values  amount for each net worth item, keyed by item id
     */
    public function handle(FinancialYear $financialYear, array $values): void
    {
        DB::transaction(function () use ($financialYear, $values): void {
            $owned = $financialYear->user->netWorthItems()->pluck('id')->all();

            foreach ($values as $itemId => $value) {
                // Ignore anything that is not this user's holding, so a crafted request
                // cannot write into someone else's year.
                if (! in_array($itemId, $owned, true)) {
                    continue;
                }

                NetWorthSnapshot::query()->updateOrCreate(
                    [
                        'net_worth_item_id' => $itemId,
                        'financial_year_id' => $financialYear->id,
                        'month' => NetWorthSnapshot::OPENING_MONTH,
                    ],
                    ['value_cents' => $value],
                );
            }
        });
    }

    /**
     * The money available at the start of the year: cash and the emergency fund, which is
     * part of the liquid balance so the app can tell when a forecast would eat into it
     * (§7.2).
     */
    public function openingLiquidBalance(FinancialYear $financialYear): Money
    {
        $cents = NetWorthSnapshot::query()
            ->where('financial_year_id', $financialYear->id)
            ->where('month', NetWorthSnapshot::OPENING_MONTH)
            ->whereHas('netWorthItem', function ($query) use ($financialYear): void {
                $query->where('user_id', $financialYear->user_id)
                    ->where('is_active', true)
                    ->whereIn('kind', CreateFinancialYear::liquidKinds());
            })
            ->sum('value_cents');

        return Money::fromCents((int) $cents);
    }

    /**
     * Everything owned minus everything owed, at the start of the year (§7.8).
     *
     * Returned as signed cents rather than a Money value: net worth is genuinely negative
     * when debts exceed assets, while Money represents stored amounts, which never are.
     * Every derived figure that can go below zero — variance, cash flow, closing balance —
     * follows the same rule.
     */
    public function openingNetWorth(FinancialYear $financialYear): int
    {
        $snapshots = NetWorthSnapshot::query()
            ->with('netWorthItem')
            ->where('financial_year_id', $financialYear->id)
            ->where('month', NetWorthSnapshot::OPENING_MONTH)
            ->get();

        $assets = 0;
        $debts = 0;

        foreach ($snapshots as $snapshot) {
            if (! $snapshot->netWorthItem->is_active) {
                continue;
            }

            // Debts are stored positive and subtracted here, so no column ever holds a
            // negative amount.
            if ($snapshot->netWorthItem->kind === NetWorthItemKind::Debt) {
                $debts += $snapshot->value_cents->cents;

                continue;
            }

            $assets += $snapshot->value_cents->cents;
        }

        return $assets - $debts;
    }
}
