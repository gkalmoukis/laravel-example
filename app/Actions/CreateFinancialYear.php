<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts a plan for a calendar year.
 *
 * The opening position is seeded with one holding per group, all at zero, so the fastest
 * path through the first wizard step is typing one or two numbers rather than building a
 * structure first (OPEN-02).
 */
final readonly class CreateFinancialYear
{
    public function __construct(private SyncSubscriptionPlanItems $subscriptionPlanItems) {}

    /**
     * Whether a year is inside the span the application offers (YEAR-01).
     */
    public static function isSelectableYear(int $year): bool
    {
        return $year >= FinancialYear::EARLIEST_YEAR
            && $year <= FinancialYear::latestSelectableYear();
    }

    /**
     * @return list<NetWorthItemKind>
     */
    public static function liquidKinds(): array
    {
        return [NetWorthItemKind::Cash, NetWorthItemKind::EmergencyFund];
    }

    public function handle(User $user, int $year): FinancialYear
    {
        return DB::transaction(function () use ($user, $year): FinancialYear {
            $financialYear = $user->financialYears()->create(['year' => $year]);

            $this->seedOpeningPosition($user, $financialYear);

            // A year opens already knowing what the standing charges will cost it
            // (SUB-04, YEAR-03).
            $this->subscriptionPlanItems->handle($financialYear);

            return $financialYear;
        });
    }

    /**
     * Holdings belong to the user rather than the year, so a second year reuses the ones
     * that already exist and only needs its own opening snapshots.
     */
    private function seedOpeningPosition(User $user, FinancialYear $financialYear): void
    {
        $sortOrder = 0;

        foreach (NetWorthItem::defaultItems() as $default) {
            $item = $user->netWorthItems()->firstOrCreate(
                ['name' => $default['name'], 'kind' => $default['kind']],
                ['sort_order' => $sortOrder],
            );

            $sortOrder++;

            $item->snapshots()->firstOrCreate(
                ['financial_year_id' => $financialYear->id, 'month' => NetWorthSnapshot::OPENING_MONTH],
                ['value_cents' => 0],
            );
        }
    }
}
