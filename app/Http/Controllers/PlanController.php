<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CapturePlanBaseline;
use App\Actions\UpdateBudgetCell;
use App\Actions\UpdateOpeningPosition;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItem;
use App\Models\SalaryModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plan, after setup: what the year expects to earn and spend (§9).
 */
final readonly class PlanController
{
    /**
     * @var list<string>
     */
    public const array TABS = ['income', 'expenses', 'irregular', 'opening'];

    public function __construct(
        private UpdateBudgetCell $budgetCell,
        private UpdateOpeningPosition $openingPosition,
        private CapturePlanBaseline $baseline,
    ) {}

    public function show(FinancialYear $year, string $tab): Response
    {
        Gate::authorize('view', $year);

        abort_unless(in_array($tab, self::TABS, true), 404);

        return Inertia::render('plan/'.$tab, [
            'tab' => $tab,
            'tabs' => self::TABS,
            'year' => [
                'year' => $year->year,
                'isSetupComplete' => $year->isSetupComplete(),
                'hasBaseline' => $year->hasBaseline(),
                'baselineCapturedAt' => $year->baseline_captured_at?->toIso8601String(),
            ],
            ...$this->tabProps($year, $tab),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tabProps(FinancialYear $year, string $tab): array
    {
        return match ($tab) {
            'income' => $this->incomeProps($year),
            'expenses' => $this->expensesProps($year),
            'irregular' => $this->irregularProps($year),
            default => $this->openingProps($year),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function incomeProps(FinancialYear $year): array
    {
        $salaryModel = $year->salaryModel()->first();

        return [
            'salaryModel' => $salaryModel instanceof SalaryModel ? [
                'name' => $salaryModel->name,
                'baseAmountCents' => $salaryModel->base_amount_cents->cents,
                'payments' => $salaryModel->payments,
            ] : null,
            'defaultPayments' => SalaryModel::defaultPayments(),
            'items' => $this->items($year, fn ($query) => $query->where('type', TransactionType::Income)),
            'categories' => $this->categories($year, TransactionType::Income),
            'summary' => $this->baseline->build($year),
        ];
    }

    /**
     * The budget grid: every expense category against the twelve months (BUD-02).
     *
     * @return array<string, mixed>
     */
    private function expensesProps(FinancialYear $year): array
    {
        $items = $year->planItems()
            ->with('amounts')
            ->where('type', TransactionType::Expense)
            ->where('kind', PlanItemKind::Recurring)
            ->get();

        $rows = [];

        foreach ($this->expenseCategories($year) as $category) {
            $ofCategory = $items->where('category_id', $category->id);

            $months = array_fill_keys(range(1, 12), 0);

            foreach ($ofCategory as $item) {
                foreach ($item->amounts as $amount) {
                    $months[$amount->month] += $amount->amount_cents->cents;
                }
            }

            $rows[] = [
                'categoryId' => $category->id,
                'categoryName' => $category->name,
                'months' => $months,
                'annualCents' => array_sum($months),
                // A category with several planned items is ambiguous, so its cells show
                // the sum and cannot be edited in place (BUD-03).
                'isEditable' => $this->budgetCell->isEditable($year, $category),
                'itemCount' => $ofCategory->count(),
            ];
        }

        $footer = array_fill_keys(range(1, 12), 0);

        foreach ($rows as $row) {
            foreach ($row['months'] as $month => $cents) {
                $footer[$month] += $cents;
            }
        }

        return [
            'rows' => $rows,
            'footerMonths' => $footer,
            'footerAnnualCents' => array_sum($footer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function irregularProps(FinancialYear $year): array
    {
        return [
            'items' => $this->items($year, fn ($query) => $query->where('kind', PlanItemKind::Irregular)),
            'categories' => $this->categories($year, TransactionType::Expense),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openingProps(FinancialYear $year): array
    {
        $snapshots = $year->netWorthSnapshots()
            ->with('netWorthItem')
            ->where('month', NetWorthSnapshot::OPENING_MONTH)
            ->get();

        return [
            'holdings' => $snapshots
                ->filter(fn (NetWorthSnapshot $snapshot): bool => $snapshot->netWorthItem->is_active)
                ->sortBy(fn (NetWorthSnapshot $snapshot): int => $snapshot->netWorthItem->sort_order)
                ->map(fn (NetWorthSnapshot $snapshot): array => [
                    'id' => $snapshot->netWorthItem->id,
                    'name' => $snapshot->netWorthItem->name,
                    'kind' => $snapshot->netWorthItem->kind->value,
                    'valueCents' => $snapshot->value_cents->cents,
                ])
                ->values()
                ->all(),
            'openingLiquidCents' => $this->openingPosition->openingLiquidBalance($year)->cents,
            'openingNetWorthCents' => $this->openingPosition->openingNetWorth($year),
        ];
    }

    /**
     * @param  callable(HasMany<PlanItem, FinancialYear>):mixed  $filter
     * @return array<int, array<string, mixed>>
     */
    private function items(FinancialYear $year, callable $filter): array
    {
        $query = $year->planItems()->with(['amounts', 'category']);

        $filter($query);

        return $query->orderBy('sort_order')->get()
            ->map(fn (PlanItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'categoryId' => $item->category_id,
                'categoryName' => $item->category->name,
                'frequency' => $item->frequency->value,
                'startMonth' => $item->start_month,
                'allocation' => $item->allocation->value,
                'isSpread' => $item->isSpread(),
                'source' => $item->source->value,
                'isManual' => $item->isManual(),
                'months' => $item->amounts
                    ->sortBy('month')
                    ->mapWithKeys(fn ($amount): array => [$amount->month => $amount->amount_cents->cents])
                    ->all(),
                'annualCents' => $item->amounts->sum(fn ($amount): int => $amount->amount_cents->cents),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(FinancialYear $year, TransactionType $type): array
    {
        return $year->user->categories()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'isIrregular' => $category->is_irregular,
            ])
            ->all();
    }

    /**
     * @return Collection<int, Category>
     */
    private function expenseCategories(FinancialYear $year)
    {
        return $year->user->categories()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where('type', TransactionType::Expense)
            ->orderBy('sort_order')
            ->get();
    }
}
