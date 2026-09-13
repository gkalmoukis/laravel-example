<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sets one month's budget for one category, from the grid (BUD-03).
 *
 * A category usually has a single manual recurring item, and the cell edits that item's
 * month. A category with none gets one created on first entry, named after the category,
 * so the grid can be filled in without visiting a form first. A category with several
 * items is ambiguous — the cell shows their sum and is read-only, because there is no way
 * to know which item the number belongs to.
 */
final readonly class UpdateBudgetCell
{
    public function __construct(private SyncPlanItemAmounts $amounts) {}

    public function handle(FinancialYear $financialYear, Category $category, int $month, Money $amount): PlanItem
    {
        return DB::transaction(function () use ($financialYear, $category, $month, $amount): PlanItem {
            $items = $this->editableItems($financialYear, $category);

            throw_if($items->count() > 1, RuntimeException::class, 'This category has several planned items, so edit them individually.');

            $planItem = $items->first() ?? $this->createItemFor($financialYear, $category);

            $this->amounts->setMonth($planItem, $month, $amount);

            return $planItem;
        });
    }

    /**
     * Whether the grid may edit this category's cells directly.
     */
    public function isEditable(FinancialYear $financialYear, Category $category): bool
    {
        return $this->editableItems($financialYear, $category)->count() <= 1;
    }

    /**
     * @return Collection<int, PlanItem>
     */
    private function editableItems(FinancialYear $financialYear, Category $category): Collection
    {
        return $financialYear->planItems()
            ->where('category_id', $category->id)
            ->where('source', PlanItemSource::Manual)
            ->where('kind', PlanItemKind::Recurring)
            ->get();
    }

    private function createItemFor(FinancialYear $financialYear, Category $category): PlanItem
    {
        $highestSortOrder = $financialYear->planItems()->max('sort_order');

        $planItem = $financialYear->planItems()->create([
            'type' => $category->type,
            'kind' => PlanItemKind::Recurring,
            'category_id' => $category->id,
            'name' => $category->name,
            'frequency' => Frequency::Monthly,
            'start_month' => 1,
            'is_fixed' => false,
            'allocation' => Allocation::LumpSum,
            'source' => PlanItemSource::Manual,
            'sort_order' => is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0,
        ]);

        // Twelve zeroes, so the month about to be set is the only non-zero one.
        $this->amounts->handle($planItem, []);

        return $planItem;
    }
}
