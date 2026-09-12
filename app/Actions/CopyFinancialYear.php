<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PlanItemSource;
use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItem;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Starts a year from last year's plan rather than from nothing (YEAR-03).
 *
 * Carries over what the user built: their own plan items with all twelve amounts, and the
 * salary arrangement. Items generated from subscriptions are not copied — those are
 * regenerated from the subscriptions themselves, so copying them would double them.
 * Transactions are never copied: they are what actually happened, not a plan.
 */
final readonly class CopyFinancialYear
{
    public function __construct(
        private CreateFinancialYear $createYear,
        private SaveSalaryModel $saveSalaryModel,
    ) {}

    public function handle(User $user, FinancialYear $source, int $year): FinancialYear
    {
        return DB::transaction(function () use ($user, $source, $year): FinancialYear {
            $target = $this->createYear->handle($user, $year);

            $target->forceFill(['copied_from_id' => $source->id])->save();

            $this->copyPlanItems($source, $target);
            $this->copySalaryModel($source, $target);
            $this->carryOpeningPositionForward($source, $target);

            return $target;
        });
    }

    /**
     * Only the user's own items. Generated ones belong to their source and are rebuilt
     * from it.
     */
    private function copyPlanItems(FinancialYear $source, FinancialYear $target): void
    {
        $items = $source->planItems()
            ->with('amounts')
            ->where('source', PlanItemSource::Manual)
            ->get();

        foreach ($items as $item) {
            $copy = $target->planItems()->create([
                'type' => $item->type,
                'kind' => $item->kind,
                'category_id' => $item->category_id,
                'subcategory_id' => $item->subcategory_id,
                'name' => $item->name,
                'frequency' => $item->frequency,
                'start_month' => $item->start_month,
                'payment_day' => $item->payment_day,
                'is_fixed' => $item->is_fixed,
                'allocation' => $item->allocation,
                'source' => PlanItemSource::Manual,
                'notes' => $item->notes,
                'sort_order' => $item->sort_order,
            ]);

            $this->copyAmounts($item, $copy);
        }
    }

    private function copyAmounts(PlanItem $from, PlanItem $to): void
    {
        $rows = $from->amounts
            ->map(fn ($amount): array => [
                'plan_item_id' => $to->id,
                'month' => $amount->month,
                'amount_cents' => $amount->amount_cents->cents,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            $to->amounts()->getConnection()->table('plan_item_amounts')->insert($rows);
        }
    }

    /**
     * Re-saved through the same action that maintains it, so the new year's generated
     * income is built rather than copied.
     */
    private function copySalaryModel(FinancialYear $source, FinancialYear $target): void
    {
        $salaryModel = $source->salaryModel()->first();

        if ($salaryModel === null) {
            return;
        }

        $this->saveSalaryModel->handle(
            $target,
            $salaryModel->base_amount_cents,
            $salaryModel->payments,
            $salaryModel->name,
        );
    }

    /**
     * The new year opens where the old one closed, using December's recorded values.
     *
     * When December was never recorded the opening position stays at zero for now. Once
     * actuals exist, the closing balance the transactions imply becomes the better
     * starting point; that arrives with the milestone that computes it.
     */
    private function carryOpeningPositionForward(FinancialYear $source, FinancialYear $target): void
    {
        $december = NetWorthSnapshot::query()
            ->where('financial_year_id', $source->id)
            ->where('month', 12)
            ->get()
            ->keyBy('net_worth_item_id');

        if ($december->isEmpty()) {
            return;
        }

        foreach ($target->netWorthSnapshots()->where('month', NetWorthSnapshot::OPENING_MONTH)->get() as $opening) {
            $closing = $december->get($opening->net_worth_item_id);

            if ($closing !== null) {
                $opening->update(['value_cents' => Money::fromCents($closing->value_cents->cents)]);
            }
        }
    }
}
