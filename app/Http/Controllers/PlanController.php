<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CapturePlanBaseline;
use App\Actions\PresentOpeningPosition;
use App\Actions\PresentSalaryModel;
use App\Actions\UpdateBudgetCell;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItem;
use App\Models\PlanItemAmount;
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
        private PresentOpeningPosition $openingPosition,
        private PresentSalaryModel $salaryModel,
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
            // Every tab shows what the plan adds up to, not just the one that happened
            // to be built first (UX-05).
            'planTotals' => $this->planTotals($year),
            'emptyTabs' => $this->emptyTabs($year),
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
            default => $this->openingPosition->handle($year),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function incomeProps(FinancialYear $year): array
    {
        return [
            'salaryModel' => $this->salaryModel->handle($year),
            'defaultPayments' => SalaryModel::defaultPayments(),
            'items' => $this->items($year, fn (HasMany $query): HasMany => $query->where('type', TransactionType::Income)),
            'categories' => $this->categories($year, TransactionType::Income),
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

        $subscriptionNames = $this->activeSubscriptionNames($year);

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
                'doubleCounts' => $this->doubleCounts($ofCategory, $subscriptionNames),
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
            'items' => $this->items($year, fn (HasMany $query): HasMany => $query->where('kind', PlanItemKind::Irregular)),
            'categories' => $this->categories($year, TransactionType::Expense),
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
                'paymentDay' => $item->payment_day,
                // Resolved rather than repeated: the 31st is the 28th in February, and
                // the list should say the day the money actually leaves (EDGE-05).
                'dueOn' => $item->paymentDateIn($item->start_month)?->toDateString(),
                'allocation' => $item->allocation->value,
                'isSpread' => $item->isSpread(),
                'source' => $item->source->value,
                'isManual' => $item->isManual(),
                'months' => $item->amounts
                    ->sortBy('month')
                    ->mapWithKeys(fn (PlanItemAmount $amount): array => [$amount->month => $amount->amount_cents->cents])
                    ->all(),
                'annualCents' => $item->amounts->sum(fn (PlanItemAmount $amount): int => $amount->amount_cents->cents),
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
     * Manual plan items that name something already planned by a subscription (SUB-05).
     *
     * A subscription plans itself, so a hand-written item of the same name in the same
     * category is almost certainly the same cost entered twice. Matched on the name alone
     * and case-insensitively, because that is how a person would notice it themselves.
     *
     * @param  Collection<int, PlanItem>  $items
     * @param  array<string, string>  $subscriptionNames  lowercased name => name as typed
     * @return list<string>
     */
    private function doubleCounts(Collection $items, array $subscriptionNames): array
    {
        $names = [];

        foreach ($items as $item) {
            if (! $item->isManual()) {
                continue;
            }

            $match = $subscriptionNames[mb_strtolower($item->name)] ?? null;

            if ($match !== null && ! in_array($match, $names, true)) {
                $names[] = $match;
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function activeSubscriptionNames(FinancialYear $year): array
    {
        $names = [];

        foreach ($year->user->subscriptions()->where('is_active', true)->get() as $subscription) {
            $names[mb_strtolower($subscription->name)] = $subscription->name;
        }

        return $names;
    }

    /**
     * @return Collection<int, Category>
     */
    private function expenseCategories(FinancialYear $year): Collection
    {
        return $year->user->categories()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where('type', TransactionType::Expense)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * What the plan adds up to over the year (FC-06).
     *
     * The same figures `CapturePlanBaseline` freezes, read back into the camelCase the
     * rest of the interface speaks — the snake_case keys are the stored baseline's
     * shape, and the screen should not have to know that.
     *
     * @return array<string, int>
     */
    private function planTotals(FinancialYear $year): array
    {
        $summary = $this->baseline->build($year);

        return [
            'incomeCents' => $this->toInt($summary['annual_income_cents'] ?? 0),
            'expensesCents' => $this->toInt($summary['annual_expenses_cents'] ?? 0),
            'savingsCents' => $this->toInt($summary['annual_savings_cents'] ?? 0),
            'yearEndCents' => $this->toInt($summary['year_end_balance_cents'] ?? 0),
        ];
    }

    /**
     * The tabs with nothing in them yet, so the plan can name what is left to do rather
     * than leaving the user to find it (UX-05, EDGE-02).
     *
     * @return list<string>
     */
    private function emptyTabs(FinancialYear $year): array
    {
        $empty = [];

        if (! $year->planItems()->where('type', TransactionType::Income)->exists()) {
            $empty[] = 'income';
        }

        if (! $year->planItems()->where('type', TransactionType::Expense)->where('kind', PlanItemKind::Recurring)->exists()) {
            $empty[] = 'expenses';
        }

        if (! $year->planItems()->where('kind', PlanItemKind::Irregular)->exists()) {
            $empty[] = 'irregular';
        }

        if (! $year->netWorthSnapshots()->where('month', NetWorthSnapshot::OPENING_MONTH)->where('value_cents', '>', 0)->exists()) {
            $empty[] = 'opening';
        }

        return $empty;
    }

    private function toInt(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
