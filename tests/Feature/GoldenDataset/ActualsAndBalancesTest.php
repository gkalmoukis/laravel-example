<?php

declare(strict_types=1);

use App\Actions\CalculateAnnualSummary;
use App\Actions\CalculateCashFlow;
use App\Actions\CalculateMonthlyFigures;
use App\Actions\CalculateVariances;
use App\Data\CategoryFigures;
use App\Data\MonthlyFigures;
use App\Data\Variance;
use App\Data\VarianceReport;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Enums\VarianceStatus;
use Carbon\CarbonImmutable;
use Tests\Fixtures\GoldenYear;

/*
 * TST-02: one fully specified year, checked against figures worked out by hand.
 *
 * The point is not to re-test each rule — the unit tests do that — but to check the rules
 * still agree when they all apply to the same year at once.
 *
 * "Today" is pinned to 15 March 2027, inside the year and inside its in-progress month.
 */

function goldenToday(): CarbonImmutable
{
    return CarbonImmutable::parse('2027-03-15');
}

function goldenCategory(MonthlyFigures $figures, string $name): CategoryFigures
{
    foreach ($figures->categories as $category) {
        if ($category->categoryName === $name) {
            return $category;
        }
    }

    throw new RuntimeException('No figures for '.$name);
}

function goldenVariance(VarianceReport $report, string $name): Variance
{
    foreach ([...$report->expenses, ...$report->income] as $variance) {
        if ($variance->categoryName === $name) {
            return $variance;
        }
    }

    throw new RuntimeException('No variance for '.$name);
}

it('reports the plan the year was given', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    $salary = goldenCategory($figures, 'Salary');
    $freelance = goldenCategory($figures, 'Freelance & Projects');
    $holidays = goldenCategory($figures, 'Holidays');

    expect($salary->plannedFor(1))->toBe(GoldenYear::SALARY_PLAN)
        ->and($salary->plannedYear())->toBe(12 * GoldenYear::SALARY_PLAN)
        // Freelance is planned twice and nowhere else.
        ->and($freelance->plannedFor(3))->toBe(GoldenYear::FREELANCE_PLAN)
        ->and($freelance->plannedFor(9))->toBe(GoldenYear::FREELANCE_PLAN)
        ->and($freelance->plannedFor(4))->toBe(0)
        // 1.200,00 spread across twelve months is 100,00 each, summing back exactly.
        ->and($holidays->plannedFor(1))->toBe(GoldenYear::HOLIDAY_MONTHLY)
        ->and($holidays->plannedYear())->toBe(GoldenYear::HOLIDAY_ANNUAL);
});

it('totals the planned year', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    $income = 0;
    $expense = 0;

    for ($month = 1; $month <= 12; $month++) {
        $income += $figures->month($month)->plannedIncomeCents;
        $expense += $figures->month($month)->plannedExpenseCents;
    }

    expect($income)->toBe(GoldenYear::PLAN_INCOME_ANNUAL_TOTAL)
        ->and($expense)->toBe(GoldenYear::PLAN_EXPENSE_ANNUAL)
        ->and($figures->month(1)->plannedExpenseCents)->toBe(GoldenYear::PLAN_EXPENSE_MONTHLY);
});

it('reports what really happened', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    $food = goldenCategory($figures, 'Food & Groceries');
    $holidays = goldenCategory($figures, 'Holidays');

    expect($food->actualFor(1))->toBe(GoldenYear::ACTUAL_FOOD[1])
        ->and($food->actualFor(2))->toBe(GoldenYear::ACTUAL_FOOD[2])
        ->and($food->actualFor(4))->toBe(0)
        // The whole holiday lands in March, not spread.
        ->and($holidays->actualFor(3))->toBe(GoldenYear::HOLIDAY_ANNUAL)
        ->and($holidays->actualFor(7))->toBe(0);
});

it('knows where each month stands', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    expect($figures->month(1)->status)->toBe(MonthStatus::Complete)
        ->and($figures->month(2)->status)->toBe(MonthStatus::Complete)
        ->and($figures->month(3)->status)->toBe(MonthStatus::InProgress)
        ->and($figures->month(4)->status)->toBe(MonthStatus::NotStarted);
});

it('forecasts the year the way the rules say', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    $food = goldenCategory($figures, 'Food & Groceries');
    $holidays = goldenCategory($figures, 'Holidays');

    expect($food->forecastFor(1))->toBe(GoldenYear::ACTUAL_FOOD[1])
        // February is complete and over plan: what happened stands.
        ->and($food->forecastFor(2))->toBe(GoldenYear::ACTUAL_FOOD[2])
        // March is in progress and over plan: the larger figure wins.
        ->and($food->forecastFor(3))->toBe(GoldenYear::ACTUAL_FOOD[3])
        ->and($food->forecastFor(4))->toBe(GoldenYear::FOOD_PLAN)
        // The holiday was paid in March, far above the 100,00 set aside for it.
        ->and($holidays->forecastFor(3))->toBe(GoldenYear::HOLIDAY_ANNUAL)
        ->and($holidays->forecastFor(7))->toBe(GoldenYear::HOLIDAY_MONTHLY);
});

it('totals the forecast year', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    $income = 0;
    $expense = 0;

    for ($month = 1; $month <= 12; $month++) {
        $income += $figures->month($month)->forecastIncomeCents;
        $expense += $figures->month($month)->forecastExpenseCents;
    }

    expect($income)->toBe(GoldenYear::FORECAST_INCOME_ANNUAL)
        ->and($expense)->toBe(GoldenYear::FORECAST_EXPENSE_ANNUAL);
});

it('runs the balance through the whole year', function (): void {
    [, $year] = GoldenYear::build();

    $flow = resolve(CalculateCashFlow::class)->handle($year, goldenToday());

    expect($flow->openingBalanceCents)->toBe(GoldenYear::OPENING_BALANCE);

    foreach (GoldenYear::PLANNED_CLOSING as $month => $cents) {
        expect($flow->month($month)->planned->closingCents)->toBe($cents);
    }

    expect($flow->plannedYearEndCents())->toBe(GoldenYear::PLANNED_YEAR_END)
        ->and($flow->forecastYearEndCents())->toBe(GoldenYear::FORECAST_YEAR_END);
});

it('runs the real balance up to the month being lived through', function (): void {
    [, $year] = GoldenYear::build();

    $flow = resolve(CalculateCashFlow::class)->handle($year, goldenToday());

    expect($flow->lastActualMonth)->toBe(3);

    foreach (GoldenYear::ACTUAL_CLOSING as $month => $cents) {
        expect($flow->month($month)->actual?->closingCents)->toBe($cents);
    }

    // Nothing beyond March has been reached, so there is no actual line to show.
    expect($flow->month(4)->actual)->toBeNull();
});

it('chains each closing balance into the next opening one', function (): void {
    [, $year] = GoldenYear::build();

    $flow = resolve(CalculateCashFlow::class)->handle($year, goldenToday());

    for ($month = 2; $month <= 12; $month++) {
        expect($flow->month($month)->planned->openingCents)
            ->toBe($flow->month($month - 1)->planned->closingCents)
            ->and($flow->month($month)->forecast->openingCents)
            ->toBe($flow->month($month - 1)->forecast->closingCents);
    }
});

it('reports what is available on the day', function (): void {
    [, $year] = GoldenYear::build();

    $flow = resolve(CalculateCashFlow::class)->handle($year, goldenToday());

    // Everything that has actually happened by 15 March. The March salary is paid on the
    // 25th and the holiday on the 20th, so neither is money the user has yet — which is
    // the whole point of a figure called "available now".
    $expected = GoldenYear::OPENING_BALANCE
        + GoldenYear::ACTUAL_INCOME[1] + GoldenYear::ACTUAL_INCOME[2]
        - array_sum(GoldenYear::ACTUAL_HOUSING)
        - array_sum(GoldenYear::ACTUAL_FOOD);

    expect($flow->currentAvailableCents)->toBe($expected)
        ->and($expected)->toBe(316_000);
});

it('summarises the year', function (): void {
    [, $year] = GoldenYear::build();

    $summary = resolve(CalculateAnnualSummary::class)->handle($year, goldenToday());

    expect($summary->plannedIncomeCents)->toBe(GoldenYear::PLAN_INCOME_ANNUAL_TOTAL)
        ->and($summary->plannedExpenseCents)->toBe(GoldenYear::PLAN_EXPENSE_ANNUAL)
        ->and($summary->forecastExpenseCents)->toBe(GoldenYear::FORECAST_EXPENSE_ANNUAL)
        ->and($summary->completedMonths)->toBe(2)
        // Two signed-off months on both sides of the comparison (CMP-04).
        ->and($summary->completedIncomeCents)->toBe(GoldenYear::ACTUAL_INCOME[1] + GoldenYear::ACTUAL_INCOME[2])
        ->and($summary->completedPlannedIncomeCents)->toBe(2 * GoldenYear::SALARY_PLAN)
        ->and($summary->completedPlannedExpenseCents)->toBe(2 * GoldenYear::PLAN_EXPENSE_MONTHLY);
});

it('counts everything recorded so far, signed off or not', function (): void {
    [, $year] = GoldenYear::build();

    $summary = resolve(CalculateAnnualSummary::class)->handle($year, goldenToday());

    // "So far" is bounded by today, not by the month: March's unfinished spending counts,
    // but its salary (the 25th) and holiday (the 20th) have not happened yet.
    expect($summary->actualSoFarIncomeCents)
        ->toBe(GoldenYear::ACTUAL_INCOME[1] + GoldenYear::ACTUAL_INCOME[2])
        ->and($summary->actualSoFarExpenseCents)
        ->toBe(array_sum(GoldenYear::ACTUAL_HOUSING) + array_sum(GoldenYear::ACTUAL_FOOD));
});

it('judges each category against its plan for the month', function (): void {
    [, $year] = GoldenYear::build();

    $report = resolve(CalculateVariances::class)->handle($year, 2);

    $housing = goldenVariance($report, 'Housing');
    $food = goldenVariance($report, 'Food & Groceries');

    expect($housing->plannedCents)->toBe(GoldenYear::HOUSING_PLAN)
        ->and($housing->actualCents)->toBe(GoldenYear::ACTUAL_HOUSING[2])
        ->and($housing->status)->toBe(VarianceStatus::Ok)
        // 450,00 against 400,00 is 12,5% over, past the 10% the user allows.
        ->and($food->varianceCents)->toBe(GoldenYear::ACTUAL_FOOD[2] - GoldenYear::FOOD_PLAN)
        ->and($food->status)->toBe(VarianceStatus::Over)
        ->and($food->label())->toBe('Over budget');
});

it('judges money set aside monthly on the year so far', function (): void {
    [, $year] = GoldenYear::build();

    $holidays = goldenVariance(resolve(CalculateVariances::class)->handle($year, 3), 'Holidays');

    // Three months of setting aside 100,00 against the whole 1.200,00 paid in March.
    expect($holidays->isSpread)->toBeTrue()
        ->and($holidays->plannedCents)->toBe(3 * GoldenYear::HOLIDAY_MONTHLY)
        ->and($holidays->actualCents)->toBe(GoldenYear::HOLIDAY_ANNUAL)
        ->and($holidays->status)->toBe(VarianceStatus::Over);
});

it('compares the year to date over signed-off months only', function (): void {
    [, $year] = GoldenYear::build();

    $report = resolve(CalculateVariances::class)->handle($year);

    expect($report->completedMonths)->toBe(2)
        ->and($report->isYearToDate())->toBeTrue();

    $housing = goldenVariance($report, 'Housing');
    $food = goldenVariance($report, 'Food & Groceries');

    expect($housing->plannedCents)->toBe(2 * GoldenYear::HOUSING_PLAN)
        ->and($housing->actualCents)->toBe(GoldenYear::ACTUAL_HOUSING[1] + GoldenYear::ACTUAL_HOUSING[2])
        ->and($food->plannedCents)->toBe(2 * GoldenYear::FOOD_PLAN)
        ->and($food->actualCents)->toBe(GoldenYear::ACTUAL_FOOD[1] + GoldenYear::ACTUAL_FOOD[2])
        // 830,00 against 800,00 is under the 10% threshold.
        ->and($food->status)->toBe(VarianceStatus::Warning);
});

it('puts the categories that went wrong first', function (): void {
    [, $year] = GoldenYear::build();

    $report = resolve(CalculateVariances::class)->handle($year, 3);

    // Holidays is furthest over, then food; housing is exactly on plan.
    expect($report->expenses[0]->categoryName)->toBe('Holidays')
        ->and($report->expenses[1]->categoryName)->toBe('Food & Groceries');
});

it('keeps every figure in step with every other', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);
    $flow = resolve(CalculateCashFlow::class)->handle($year, goldenToday());
    $summary = resolve(CalculateAnnualSummary::class)->handle($year, goldenToday());

    // The same numbers reached three different ways have to agree, or one of the screens
    // built on them is lying.
    $forecastExpense = 0;

    for ($month = 1; $month <= 12; $month++) {
        $forecastExpense += $figures->month($month)->forecastExpenseCents;

        expect($flow->month($month)->forecast->expenseCents)
            ->toBe($figures->month($month)->forecastExpenseCents);
    }

    expect($forecastExpense)->toBe($summary->forecastExpenseCents)
        ->and($flow->forecastYearEndCents())->toBe($summary->forecastYearEndCents)
        ->and($flow->plannedYearEndCents())->toBe($summary->plannedYearEndCents);
});

it('separates income from expenses throughout', function (): void {
    [, $year] = GoldenYear::build();

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    $income = collect($figures->categoriesOfType(TransactionType::Income))->pluck('categoryName');
    $expenses = collect($figures->categoriesOfType(TransactionType::Expense))->pluck('categoryName');

    expect($income)->toContain('Salary')
        ->toContain('Freelance & Projects')
        ->and($expenses)->toContain('Housing')
        ->toContain('Holidays')
        ->and($income->intersect($expenses))->toBeEmpty();
});
