<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records what each holding was worth at the end of a month (MON-06, NW-03).
 *
 * This is the one figure the application cannot derive. Transactions say what moved; only
 * the user can say what an investment is now worth or what is left on a debt. Month 0 is
 * the opening position and is written by UpdateOpeningPosition instead, so this handles
 * months 1 to 12.
 */
final readonly class SaveNetWorthSnapshots
{
    /**
     * @param  array<int, Money>  $values  amount for each net worth item, keyed by item id
     */
    public function handle(FinancialYear $financialYear, int $month, array $values): void
    {
        DB::transaction(function () use ($financialYear, $month, $values): void {
            $owned = $financialYear->user->netWorthItems()->pluck('id')->all();

            foreach ($values as $itemId => $value) {
                // Anything that is not this user's holding is ignored rather than
                // refused, so a crafted request cannot write into someone else's year.
                if (! in_array($itemId, $owned, true)) {
                    continue;
                }

                NetWorthSnapshot::query()->updateOrCreate(
                    [
                        'net_worth_item_id' => $itemId,
                        'financial_year_id' => $financialYear->id,
                        'month' => $month,
                    ],
                    ['value_cents' => $value],
                );
            }
        });
    }

    /**
     * What each active holding should start the form at (MON-06).
     *
     * A month usually looks much like the one before it, so the previous value is offered
     * rather than an empty box — and the value carried forward is the most recent one
     * actually recorded, which may be several months back (§7.8).
     *
     * @return array<int, int> item id to cents
     */
    public function prefill(FinancialYear $financialYear, int $month): array
    {
        $recorded = NetWorthSnapshot::query()
            ->where('financial_year_id', $financialYear->id)
            ->where('month', '<=', $month)
            ->orderBy('month')
            ->get();

        $prefill = [];

        foreach ($recorded as $snapshot) {
            // Ordered by month, so the last write for an item is the latest value at or
            // before this month.
            $prefill[$snapshot->net_worth_item_id] = $snapshot->value_cents->cents;
        }

        return $prefill;
    }
}
