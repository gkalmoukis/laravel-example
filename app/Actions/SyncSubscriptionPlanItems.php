<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Allocation;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\Models\Subscription;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the plan in step with what is actually charged on a schedule (SUB-04).
 *
 * A subscription's plan item is owned by the subscription: it is rebuilt from the billing
 * sequence rather than edited by hand, so the plan and the subscription can never drift
 * apart and the same cost is never entered twice (SUB-05).
 */
final readonly class SyncSubscriptionPlanItems
{
    public function __construct(private SyncPlanItemAmounts $amounts) {}

    /**
     * Rebuilds one year's generated items from the user's subscriptions.
     *
     * Subscriptions with no charge in this year — stopped before it began, or not started
     * until after it ends — leave no item behind: a row of twelve zeroes is noise.
     */
    public function handle(FinancialYear $financialYear): void
    {
        DB::transaction(function () use ($financialYear): void {
            $kept = [];
            $sortOrder = 0;

            foreach ($this->subscriptions($financialYear->user) as $subscription) {
                $months = $subscription->billingMonthsIn($financialYear->year);

                if ($months === []) {
                    continue;
                }

                $kept[] = $this->writeItem($financialYear, $subscription, $months, $sortOrder)->id;

                $sortOrder++;
            }

            $this->removeStale($financialYear, $kept);
        });
    }

    /**
     * Re-syncs after a subscription changed (SUB-04).
     *
     * Only the current year and the ones after it: a past year is a record of what was
     * planned at the time, and rewriting it would change history the user has already
     * compared their spending against.
     */
    public function forUser(User $user, CarbonInterface $today): void
    {
        $years = $user->financialYears()->where('year', '>=', $today->year)->get();

        foreach ($years as $financialYear) {
            $this->handle($financialYear);
        }
    }

    /**
     * @param  list<int>  $months
     */
    private function writeItem(FinancialYear $financialYear, Subscription $subscription, array $months, int $sortOrder): PlanItem
    {
        $planItem = $financialYear->planItems()->updateOrCreate(
            ['source' => PlanItemSource::Subscription, 'subscription_id' => $subscription->id],
            [
                'type' => TransactionType::Expense,
                'kind' => PlanItemKind::Recurring,
                'category_id' => $subscription->category_id,
                'subcategory_id' => $subscription->subcategory_id,
                'name' => $subscription->name,
                'frequency' => $subscription->frequency,
                'start_month' => $months[0],
                'is_fixed' => true,
                'allocation' => Allocation::LumpSum,
                'sort_order' => $sortOrder,
            ],
        );

        $this->amounts->handle($planItem, $this->schedule($subscription->amount_cents, $months));

        return $planItem;
    }

    /**
     * The amount in each month it is charged, and nothing in the rest.
     *
     * Months after a cancellation are simply not billing months, which is what zeroes
     * them (SUB-04).
     *
     * @param  list<int>  $months
     * @return array<int, Money>
     */
    private function schedule(Money $amount, array $months): array
    {
        $schedule = [];

        for ($month = 1; $month <= SyncPlanItemAmounts::MONTHS; $month++) {
            $schedule[$month] = in_array($month, $months, true) ? $amount : Money::zero();
        }

        return $schedule;
    }

    /**
     * Items whose subscription no longer charges in this year, left over from an earlier
     * sync.
     *
     * @param  list<int>  $kept
     */
    private function removeStale(FinancialYear $financialYear, array $kept): void
    {
        $financialYear->planItems()
            ->where('source', PlanItemSource::Subscription)
            ->whereNotIn('id', $kept)
            ->delete();
    }

    /**
     * Ordered by name so the generated rows keep a stable order between syncs.
     *
     * @return Collection<int, Subscription>
     */
    private function subscriptions(User $user): Collection
    {
        return $user->subscriptions()->orderBy('name')->orderBy('id')->get();
    }
}
