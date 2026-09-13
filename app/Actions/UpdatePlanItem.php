<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlanItem;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Edits a planned item.
 *
 * The twelve monthly amounts are only rebuilt when an amount is supplied, so renaming an
 * item or changing its notes never discards months the user has hand-edited (YEAR-06).
 */
final readonly class UpdatePlanItem
{
    public function __construct(
        private BuildPlanSchedule $schedule,
        private SyncPlanItemAmounts $amounts,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $customMonths
     */
    public function handle(PlanItem $planItem, array $attributes, ?Money $amount = null, array $customMonths = []): PlanItem
    {
        return DB::transaction(function () use ($planItem, $attributes, $amount, $customMonths): PlanItem {
            $planItem->update($attributes);

            if ($amount instanceof Money) {
                $planItem->refresh();

                $this->amounts->handle($planItem, $this->schedule->handle(
                    $planItem->frequency,
                    $amount,
                    $planItem->start_month,
                    $planItem->allocation,
                    $customMonths,
                ));
            }

            return $planItem;
        });
    }

    /**
     * Sets one month directly, which is what editing a cell in the budget grid does.
     */
    public function setMonth(PlanItem $planItem, int $month, Money $amount): PlanItem
    {
        $this->amounts->setMonth($planItem, $month, $amount);

        return $planItem;
    }

    /**
     * Whether an item's schedule follows from a single amount, or has been shaped month
     * by month. Used to warn before regenerating (INC-06).
     */
    public function hasHandEditedMonths(PlanItem $planItem, Money $amount): bool
    {
        $generated = $this->schedule->handle(
            $planItem->frequency,
            $amount,
            $planItem->start_month,
            $planItem->allocation,
        );

        foreach ($planItem->amounts()->get() as $stored) {
            if (! $stored->amount_cents->equals($generated[$stored->month] ?? Money::zero())) {
                return true;
            }
        }

        return false;
    }
}
