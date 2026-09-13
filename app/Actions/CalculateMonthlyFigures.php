<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CategoryFigures;
use App\Data\MonthlyFigures;
use App\Data\MonthTotals;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\PlanItemAmount;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

/**
 * Plan, Actual and Forecast for every category and month of a year (§7.1).
 *
 * This is the root every other figure grows from: cash flow, variance, the forecast and
 * the dashboard all read these numbers rather than recomputing them, so the rules live in
 * one place and cannot drift between screens.
 *
 * Three grouped aggregates do the work — planned amounts, actual amounts, and which months
 * have been finished — rather than a query per category or per month. Later Actions that
 * need to know what day it is take `today` as an argument (see CalculateCashFlow); this one
 * does not, because nothing in §7.1 depends on the date the question is asked.
 */
final readonly class CalculateMonthlyFigures
{
    /**
     * @var list<int>
     */
    private const array MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    public function handle(FinancialYear $financialYear): MonthlyFigures
    {
        $planned = $this->plannedByCategoryAndMonth($financialYear);
        $actual = $this->actualByCategoryAndMonth($financialYear);
        $statuses = $this->statuses($financialYear);

        $categories = [];

        foreach ($this->categories($financialYear) as $category) {
            $categories[] = $this->figuresFor(
                $category,
                $planned[$category->id] ?? [],
                $actual[$category->id] ?? [],
                $statuses,
            );
        }

        return new MonthlyFigures(
            year: $financialYear->year,
            categories: $categories,
            months: $this->totals($categories, $statuses),
        );
    }

    /**
     * @param  array<int, int>  $planned
     * @param  array<int, int>  $actual
     * @param  array<int, MonthStatus>  $statuses
     */
    private function figuresFor(Category $category, array $planned, array $actual, array $statuses): CategoryFigures
    {
        $plan = [];
        $actuals = [];
        $forecast = [];

        foreach (self::MONTHS as $month) {
            $p = $planned[$month] ?? 0;
            $a = $actual[$month] ?? 0;

            $plan[$month] = $p;
            $actuals[$month] = $a;

            // A finished month is what actually happened. An unfinished one keeps its
            // plan, but real spending that has already passed the plan is not wished
            // away — hence the larger of the two (Q-04).
            $forecast[$month] = $statuses[$month] === MonthStatus::Complete
                ? $a
                : max($p, $a);
        }

        return new CategoryFigures(
            categoryId: $category->id,
            categoryName: $category->name,
            type: $category->type,
            plan: $plan,
            actual: $actuals,
            forecast: $forecast,
        );
    }

    /**
     * @param  list<CategoryFigures>  $categories
     * @param  array<int, MonthStatus>  $statuses
     * @return array<int, MonthTotals>
     */
    private function totals(array $categories, array $statuses): array
    {
        $months = [];

        foreach (self::MONTHS as $month) {
            $sums = [
                'plannedIncome' => 0, 'plannedExpense' => 0,
                'actualIncome' => 0, 'actualExpense' => 0,
                'forecastIncome' => 0, 'forecastExpense' => 0,
            ];

            foreach ($categories as $figures) {
                $suffix = $figures->type === TransactionType::Income ? 'Income' : 'Expense';

                $sums['planned'.$suffix] += $figures->plannedFor($month);
                $sums['actual'.$suffix] += $figures->actualFor($month);
                $sums['forecast'.$suffix] += $figures->forecastFor($month);
            }

            $months[$month] = new MonthTotals(
                month: $month,
                status: $statuses[$month],
                plannedIncomeCents: $sums['plannedIncome'],
                plannedExpenseCents: $sums['plannedExpense'],
                actualIncomeCents: $sums['actualIncome'],
                actualExpenseCents: $sums['actualExpense'],
                forecastIncomeCents: $sums['forecastIncome'],
                forecastExpenseCents: $sums['forecastExpense'],
            );
        }

        return $months;
    }

    /**
     * Every top-level category the user has, including deactivated ones: a category can be
     * retired, but the history filed under it stays visible (CAT-04).
     *
     * @return Collection<int, Category>
     */
    private function categories(FinancialYear $financialYear): Collection
    {
        return $financialYear->user->categories()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Aggregates arrive as mixed — MySQL hands SUM() back as a string — so this narrows
     * one rather than casting a value whose type is not known.
     */
    private function toInt(mixed $value): int
    {
        return (int) (is_numeric($value) ? $value : 0);
    }

    /**
     * @return array<int, array<int, int>> category id to month to cents
     */
    private function plannedByCategoryAndMonth(FinancialYear $financialYear): array
    {
        $rows = PlanItemAmount::query()
            ->join('plan_items', 'plan_items.id', '=', 'plan_item_amounts.plan_item_id')
            ->where('plan_items.financial_year_id', $financialYear->id)
            ->groupBy('plan_items.category_id', 'plan_item_amounts.month')
            ->get([
                'plan_items.category_id as category_id',
                'plan_item_amounts.month as month',
                PlanItemAmount::query()->raw('SUM(plan_item_amounts.amount_cents) as total_cents'),
            ]);

        $planned = [];

        foreach ($rows as $row) {
            $planned[$this->toInt($row->getAttribute('category_id'))][$this->toInt($row->getAttribute('month'))]
                = $this->toInt($row->getAttribute('total_cents'));
        }

        return $planned;
    }

    /**
     * Only valid transactions count. A flagged one is listed and explained but never
     * added up, because counting a number the user has not finished correcting would be
     * worse than leaving it out (TXV-05).
     *
     * @return array<int, array<int, int>> category id to month to cents
     */
    private function actualByCategoryAndMonth(FinancialYear $financialYear): array
    {
        $rows = Transaction::valid()
            ->where('transactions.user_id', $financialYear->user_id)
            ->whereYear('occurred_on', $financialYear->year)
            ->groupBy('category_id', 'month')
            ->get([
                'category_id',
                Transaction::query()->raw('MONTH(occurred_on) as month'),
                Transaction::query()->raw('SUM(amount_cents) as total_cents'),
            ]);

        $actual = [];

        foreach ($rows as $row) {
            $actual[$this->toInt($row->getAttribute('category_id'))][$this->toInt($row->getAttribute('month'))]
                = $this->toInt($row->getAttribute('total_cents'));
        }

        return $actual;
    }

    /**
     * Status is derived, never stored: only completion is recorded, and a month with
     * anything in it is in progress by definition (MON-01).
     *
     * @return array<int, MonthStatus>
     */
    private function statuses(FinancialYear $financialYear): array
    {
        $completed = MonthClosure::query()
            ->where('financial_year_id', $financialYear->id)
            ->whereNotNull('completed_at')
            ->pluck('month')
            ->all();

        $touched = Transaction::query()
            ->where('user_id', $financialYear->user_id)
            ->whereYear('occurred_on', $financialYear->year)
            ->groupBy('month')
            ->get([Transaction::query()->raw('MONTH(occurred_on) as month')]);

        $started = [];

        foreach ($touched as $row) {
            $started[] = $this->toInt($row->getAttribute('month'));
        }

        $statuses = [];

        foreach (self::MONTHS as $month) {
            $statuses[$month] = match (true) {
                in_array($month, $completed, true) => MonthStatus::Complete,
                in_array($month, $started, true) => MonthStatus::InProgress,
                default => MonthStatus::NotStarted,
            };
        }

        return $statuses;
    }
}
