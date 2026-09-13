<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Adds something the user owns or owes (NW-02).
 *
 * A new holding opens at zero in every year the user has, so it appears in their net
 * worth from the start rather than only from the first month they happen to value it.
 */
final readonly class CreateNetWorthItem
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): NetWorthItem
    {
        return DB::transaction(function () use ($user, $attributes): NetWorthItem {
            $highestSortOrder = $user->netWorthItems()->max('sort_order');

            $item = $user->netWorthItems()->create([
                ...$attributes,
                'is_active' => true,
                'sort_order' => is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0,
            ]);

            foreach ($user->financialYears()->get() as $year) {
                $this->openAtZero($item, $year);
            }

            return $item;
        });
    }

    private function openAtZero(NetWorthItem $item, FinancialYear $year): void
    {
        NetWorthSnapshot::query()->firstOrCreate(
            [
                'net_worth_item_id' => $item->id,
                'financial_year_id' => $year->id,
                'month' => NetWorthSnapshot::OPENING_MONTH,
            ],
            ['value_cents' => Money::zero()],
        );
    }
}
