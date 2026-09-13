<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateCashFlow;
use App\Actions\CalculateMonthlyFigures;
use App\Actions\CalculateVariances;
use App\Data\BalanceLine;
use App\Data\Variance;
use App\Enums\TransactionIssue;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class MonthController
{
    private const int MONTHS = 12;

    /**
     * The year at a glance: twelve cards saying what each month came to and whether it has
     * been finished (MON-02).
     */
    public function index(FinancialYear $year, CalculateMonthlyFigures $figures): Response
    {
        Gate::authorize('view', $year);

        $monthly = $figures->handle($year);
        $issues = $this->issueCounts($year);

        $months = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $totals = $monthly->month($month);

            $months[] = [
                'month' => $month,
                'status' => $totals->status->value,
                'incomeCents' => $totals->actualIncomeCents,
                'expenseCents' => $totals->actualExpenseCents,
                'netCents' => $totals->actualNetCents(),
                'issueCount' => $issues[$month] ?? 0,
            ];
        }

        return Inertia::render('months/index', [
            'year' => $year->year,
            'months' => $months,
        ]);
    }

    /**
     * One month, in the order someone closing it works through: what it came to, what is
     * still wrong with it, and where it drifted from the plan (MON-03).
     */
    public function show(
        FinancialYear $year,
        int $month,
        CalculateMonthlyFigures $figures,
        CalculateVariances $variances,
        CalculateCashFlow $cashFlow,
    ): Response {
        Gate::authorize('view', $year);

        abort_unless($month >= 1 && $month <= self::MONTHS, 404);

        $monthly = $figures->handle($year);
        $totals = $monthly->month($month);
        $flow = $cashFlow->handle($year, $year->user->today())->month($month);
        $report = $variances->handle($year, $month);
        $breakdown = $this->subcategoryBreakdown($year, $month);

        // Before the user has reached this month there is no actual balance, so the
        // forecast line stands in — and the page is told which it is showing.
        $balance = $flow->actual ?? $flow->forecast;

        return Inertia::render('months/show', [
            'year' => $year->year,
            'month' => $month,
            'status' => $totals->status->value,
            'totals' => [
                'incomeCents' => $totals->actualIncomeCents,
                'expenseCents' => $totals->actualExpenseCents,
                'netCents' => $totals->actualNetCents(),
                'plannedIncomeCents' => $totals->plannedIncomeCents,
                'plannedExpenseCents' => $totals->plannedExpenseCents,
                'plannedNetCents' => $totals->plannedNetCents(),
                'openingCents' => $balance->openingCents,
                'closingCents' => $balance->closingCents,
                'hasActual' => $flow->actual instanceof BalanceLine,
            ],
            'issues' => $this->issues($year, $month),
            'income' => $this->presentVariances($report->income, $breakdown),
            'expenses' => $this->presentVariances($report->expenses, $breakdown),
        ]);
    }

    /**
     * The flagged transactions of this month, each with what is wrong (MON-03 step 2).
     *
     * @return list<array<string, mixed>>
     */
    private function issues(FinancialYear $year, int $month): array
    {
        $flagged = Transaction::withIssues(Transaction::flagged())
            ->where('transactions.user_id', $year->user_id)
            ->whereYear('occurred_on', $year->year)
            ->whereMonth('occurred_on', $month)
            ->with(['category', 'subcategory'])
            ->orderBy('occurred_on')
            ->get();

        $issues = [];

        foreach ($flagged as $transaction) {
            $issues[] = [
                'id' => $transaction->id,
                'occurredOn' => $transaction->occurred_on->toDateString(),
                'description' => $transaction->description,
                'amountCents' => $transaction->amount_cents->cents,
                'type' => $transaction->type->value,
                'categoryName' => $transaction->category->name,
                'reasons' => array_map(
                    fn (TransactionIssue $issue): string => $issue->reason(),
                    $transaction->loadedIssues(),
                ),
            ];
        }

        return $issues;
    }

    /**
     * @param  list<Variance>  $variances
     * @param  array<int, list<array<string, mixed>>>  $breakdown
     * @return list<array<string, mixed>>
     */
    private function presentVariances(array $variances, array $breakdown): array
    {
        return array_map(fn (Variance $variance): array => [
            'categoryId' => $variance->categoryId,
            'categoryName' => $variance->categoryName,
            'plannedCents' => $variance->plannedCents,
            'actualCents' => $variance->actualCents,
            'varianceCents' => $variance->varianceCents,
            'status' => $variance->status->value,
            'label' => $variance->label(),
            'isSpread' => $variance->isSpread,
            'subcategories' => $breakdown[$variance->categoryId] ?? [],
        ], $variances);
    }

    /**
     * What each category's spending breaks down into (CAT-08).
     *
     * Amounts filed directly against the category, with no subcategory, are shown as "No
     * subcategory" rather than quietly left out of a breakdown that should add up.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function subcategoryBreakdown(FinancialYear $year, int $month): array
    {
        $rows = Transaction::valid()
            ->where('transactions.user_id', $year->user_id)
            ->whereYear('occurred_on', $year->year)
            ->whereMonth('occurred_on', $month)
            ->groupBy('category_id', 'subcategory_id')
            ->get([
                'category_id',
                'subcategory_id',
                Transaction::query()->raw('SUM(amount_cents) as total_cents'),
            ]);

        $names = Category::query()
            ->where('user_id', $year->user_id)
            ->whereNotNull('parent_id')
            ->pluck('name', 'id');

        $breakdown = [];

        foreach ($rows as $row) {
            $categoryId = $this->toInt($row->getAttribute('category_id'));
            $subcategoryId = $row->getAttribute('subcategory_id');

            $name = $subcategoryId === null
                ? null
                : $names->get($this->toInt($subcategoryId));

            $breakdown[$categoryId][] = [
                'id' => $subcategoryId === null ? null : $this->toInt($subcategoryId),
                'name' => is_string($name) ? $name : 'No subcategory',
                'actualCents' => $this->toInt($row->getAttribute('total_cents')),
            ];
        }

        return $breakdown;
    }

    private function toInt(mixed $value): int
    {
        return (int) (is_numeric($value) ? $value : 0);
    }

    /**
     * How many transactions in each month still need putting right (MON-02, TXV-05).
     *
     * One grouped query rather than one per card.
     *
     * @return array<int, int>
     */
    private function issueCounts(FinancialYear $year): array
    {
        $rows = Transaction::flagged()
            ->where('transactions.user_id', $year->user_id)
            ->whereYear('occurred_on', $year->year)
            ->groupBy('month')
            ->get([
                Transaction::query()->raw('MONTH(occurred_on) as month'),
                Transaction::query()->raw('COUNT(*) as flagged_count'),
            ]);

        $counts = [];

        foreach ($rows as $row) {
            $month = $row->getAttribute('month');
            $count = $row->getAttribute('flagged_count');

            $counts[(int) (is_numeric($month) ? $month : 0)] = (int) (is_numeric($count) ? $count : 0);
        }

        return $counts;
    }
}
