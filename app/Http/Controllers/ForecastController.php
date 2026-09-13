<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateAnnualSummary;
use App\Actions\CalculateCashFlow;
use App\Actions\CalculateMonthlyFigures;
use App\Data\CashFlow;
use App\Data\CategoryFigures;
use App\Data\CategoryForecast;
use App\Data\MonthlyFigures;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the year is heading (FC-01 … FC-05).
 *
 * Everything here is worked out afresh on each request. There is no cached forecast to go
 * stale and no invalidation to get wrong, which is what lets a corrected transaction show
 * up in the year-end figure immediately (FC-03).
 */
final readonly class ForecastController
{
    private const int MONTHS = 12;

    public function index(
        FinancialYear $year,
        CalculateMonthlyFigures $figures,
        CalculateCashFlow $cashFlow,
        CalculateAnnualSummary $annualSummary,
    ): Response {
        Gate::authorize('view', $year);

        $today = $year->user->today();

        $monthly = $figures->handle($year);
        $flow = $cashFlow->handle($year, $today);
        $summary = $annualSummary->handle($year, $today);

        return Inertia::render('forecast/index', [
            'year' => $year->year,
            'summary' => [
                'incomeCents' => $summary->forecastIncomeCents,
                'expenseCents' => $summary->forecastExpenseCents,
                'savingsCents' => $summary->forecastSavingsCents(),
                'yearEndCents' => $summary->forecastYearEndCents,
                'plannedYearEndCents' => $summary->plannedYearEndCents,
                'deviationCents' => $summary->deviationCents,
                'hasBaseline' => $summary->hasBaseline,
                'baselineCapturedAt' => $year->baseline_captured_at?->toIso8601String(),
            ],
            'months' => $this->months($monthly, $flow, $year, $today->year, $today->month),
            'categories' => $this->categories($monthly),
        ]);
    }

    /**
     * Each month, what feeds its forecast, and whether that is a problem (FC-02, FC-04).
     *
     * @return list<array<string, mixed>>
     */
    private function months(
        MonthlyFigures $monthly,
        CashFlow $flow,
        FinancialYear $year,
        int $currentYear,
        int $currentMonth,
    ): array {
        $months = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $totals = $monthly->month($month);

            // A month already behind the user that was never signed off is still being
            // forecast from its plan, which is worth saying out loud (FC-04).
            $isPast = $year->year < $currentYear
                || ($year->year === $currentYear && $month < $currentMonth);

            $months[] = [
                'month' => $month,
                'status' => $totals->status->value,
                'source' => $totals->status->forecastSource(),
                'needsAttention' => $isPast && $totals->status !== MonthStatus::Complete,
                'incomeCents' => $totals->forecastIncomeCents,
                'expenseCents' => $totals->forecastExpenseCents,
                'netCents' => $totals->forecastNetCents(),
                'closingCents' => $flow->month($month)->forecast->closingCents,
            ];
        }

        return $months;
    }

    /**
     * Plan against forecast for the year, category by category (FC-05).
     *
     * Categories with nothing planned and nothing forecast are left out: a table of
     * mostly zeroes hides the handful of rows that matter.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function categories(MonthlyFigures $monthly): array
    {
        $income = [];
        $expenses = [];

        foreach ($monthly->categories as $category) {
            $forecast = new CategoryForecast(
                categoryId: $category->categoryId,
                categoryName: $category->categoryName,
                type: $category->type,
                plannedCents: $category->plannedYear(),
                forecastCents: $this->forecastYear($category),
            );

            if ($forecast->plannedCents === 0 && $forecast->forecastCents === 0) {
                continue;
            }

            if ($category->type === TransactionType::Income) {
                $income[] = $forecast;
            } else {
                $expenses[] = $forecast;
            }
        }

        return [
            'income' => $this->byDifference($income),
            'expenses' => $this->byDifference($expenses),
        ];
    }

    private function forecastYear(CategoryFigures $category): int
    {
        $total = 0;

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $total += $category->forecastFor($month);
        }

        return $total;
    }

    /**
     * Biggest departure from the plan first, so the rows worth reading come first.
     *
     * @param  list<CategoryForecast>  $rows
     * @return list<array<string, mixed>>
     */
    private function byDifference(array $rows): array
    {
        usort(
            $rows,
            fn (CategoryForecast $a, CategoryForecast $b): int => abs($b->differenceCents()) <=> abs($a->differenceCents()),
        );

        return array_map(fn (CategoryForecast $row): array => $row->toArray(), $rows);
    }
}
