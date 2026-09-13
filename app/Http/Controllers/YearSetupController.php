<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PresentOpeningPosition;
use App\Actions\PresentSalaryModel;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItem;
use App\Models\PlanItemAmount;
use App\Models\SalaryModel;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The setup wizard (YEAR-04).
 *
 * Progress is the data itself rather than a stored cursor: a step counts as done when it
 * has something in it, so leaving and returning resumes at the first step that does not.
 */
final readonly class YearSetupController
{
    /**
     * @var list<string>
     */
    public const array STEPS = ['opening', 'income', 'expenses'];

    public function __construct(
        private PresentOpeningPosition $openingPosition,
        private PresentSalaryModel $salaryModel,
    ) {}

    public function show(FinancialYear $year, string $step): Response
    {
        Gate::authorize('view', $year);

        abort_unless(in_array($step, self::STEPS, true), 404);

        return Inertia::render('years/setup', [
            'step' => $step,
            'steps' => self::STEPS,
            'year' => [
                'year' => $year->year,
                'isSetupComplete' => $year->isSetupComplete(),
            ],
            'completedSteps' => $this->completedSteps($year),
            ...$this->stepProps($year, $step),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stepProps(FinancialYear $year, string $step): array
    {
        return match ($step) {
            'opening' => $this->openingPosition->handle($year),
            'income' => $this->incomeProps($year),
            default => $this->planProps($year, $step),
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
            // Preselected from the user's own preference (INC-03).
            'salaryPayments' => $year->user->preference->salary_payments ?? 14,
            ...$this->planProps($year, 'income'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planProps(FinancialYear $year, string $step): array
    {
        // Only categories of the step's own type: an expense step must not offer income
        // categories, or a cost could be filed under Salary.
        $type = $step === 'income' ? TransactionType::Income : TransactionType::Expense;

        return [
            'planItems' => $year->planItems()
                ->with(['amounts', 'category'])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (PlanItem $item): array => $this->presentPlanItem($item))
                ->all(),
            'categories' => $year->user->categories()
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->where('type', $type)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                    'isIrregular' => $category->is_irregular,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPlanItem(PlanItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type->value,
            'kind' => $item->kind->value,
            'categoryId' => $item->category_id,
            'categoryName' => $item->category->name,
            'frequency' => $item->frequency->value,
            'startMonth' => $item->start_month,
            'allocation' => $item->allocation->value,
            'source' => $item->source->value,
            'isManual' => $item->isManual(),
            'months' => $item->amounts
                ->sortBy('month')
                ->mapWithKeys(fn (PlanItemAmount $amount): array => [$amount->month => $amount->amount_cents->cents])
                ->all(),
            'annualCents' => $item->amounts->sum(fn (PlanItemAmount $amount): int => $amount->amount_cents->cents),
        ];
    }

    /**
     * A step is done when it holds something, so returning resumes where the user left
     * off rather than at the start (YEAR-04).
     *
     * @return list<string>
     */
    private function completedSteps(FinancialYear $year): array
    {
        $done = [];

        if ($year->netWorthSnapshots()->where('month', NetWorthSnapshot::OPENING_MONTH)->where('value_cents', '>', 0)->exists()) {
            $done[] = 'opening';
        }

        if ($year->planItems()->where('type', TransactionType::Income)->exists()) {
            $done[] = 'income';
        }

        if ($year->planItems()->where('type', TransactionType::Expense)->where('kind', PlanItemKind::Recurring)->exists()) {
            $done[] = 'expenses';
        }

        return $done;
    }
}
