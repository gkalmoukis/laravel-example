<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlanItem;
use App\ValueObjects\Money;

/**
 * Writes a plan item's twelve monthly amounts.
 *
 * Every item always has twelve rows, zero where nothing is planned, so a reader never has
 * to infer a month from its absence.
 */
final readonly class SyncPlanItemAmounts
{
    public const int MONTHS = 12;

    /**
     * @param  array<int, Money>  $schedule  amounts keyed by month, 1 to 12
     */
    public function handle(PlanItem $planItem, array $schedule): void
    {
        $rows = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $rows[] = [
                'plan_item_id' => $planItem->id,
                'month' => $month,
                'amount_cents' => ($schedule[$month] ?? Money::zero())->cents,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // One statement rather than twelve: an item's year is always written at once.
        $planItem->amounts()->getConnection()
            ->table('plan_item_amounts')
            ->upsert($rows, ['plan_item_id', 'month'], ['amount_cents', 'updated_at']);
    }

    /**
     * Sets a single month without disturbing the other eleven.
     */
    public function setMonth(PlanItem $planItem, int $month, Money $amount): void
    {
        $planItem->amounts()->updateOrCreate(
            ['month' => $month],
            ['amount_cents' => $amount],
        );
    }
}
