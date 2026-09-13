<?php

declare(strict_types=1);

use App\Actions\CalculateMonthlyFigures;
use App\Actions\CreatePlanItem;
use App\Data\CategoryFigures;
use App\Data\MonthlyFigures;
use App\Enums\Frequency;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

/*
 * §7.1 is normative, so every expectation here is worked out by hand from the rules rather
 * than from what the code happens to produce.
 */

function figuresCategory(User $user, string $name): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

function planMonthly(FinancialYear $year, Category $category, int $cents, int $startMonth = 1): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $category->id,
        'name' => $category->name.' plan',
        'type' => $category->type->value,
        'frequency' => Frequency::Monthly,
        'start_month' => $startMonth,
    ], Money::fromCents($cents));
}

function record(User $user, Category $category, string $date, int $cents, ?Category $subcategory = null): Transaction
{
    return Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'subcategory_id' => $subcategory?->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function figures(FinancialYear $year): MonthlyFigures
{
    return resolve(CalculateMonthlyFigures::class)->handle($year);
}

function forCategory(MonthlyFigures $figures, string $name): CategoryFigures
{
    foreach ($figures->categories as $category) {
        if ($category->categoryName === $name) {
            return $category;
        }
    }

    throw new RuntimeException('No figures for '.$name);
}

it('sums the plan for a category across its items', function (): void {
    [$user, $year] = userWithYear();

    $housing = figuresCategory($user, 'Housing');

    // Two items in one category: 700,00 € a month plus 50,00 € a month is 750,00 €.
    planMonthly($year, $housing, 70_000);
    planMonthly($year, $housing, 5_000);

    $housingFigures = forCategory(figures($year), 'Housing');

    expect($housingFigures->plannedFor(1))->toBe(75_000)
        ->and($housingFigures->plannedYear())->toBe(900_000);
});

it('counts actual spending into the month its date falls in', function (): void {
    [$user, $year] = userWithYear();

    $housing = figuresCategory($user, 'Housing');

    record($user, $housing, '2027-03-01', 40_000);
    record($user, $housing, '2027-03-31', 10_000);
    record($user, $housing, '2027-04-01', 1_000);

    $housingFigures = forCategory(figures($year), 'Housing');

    expect($housingFigures->actualFor(3))->toBe(50_000)
        ->and($housingFigures->actualFor(4))->toBe(1_000)
        ->and($housingFigures->actualFor(2))->toBe(0);
});

it('rolls a subcategory up into its parent', function (): void {
    [$user, $year] = userWithYear();

    $food = figuresCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    record($user, $food, '2027-03-01', 3_000, $supermarket);
    record($user, $food, '2027-03-02', 2_000);

    // A category is the unit reports show; its breakdown is a drill-down (CAT-08).
    expect(forCategory(figures($year), 'Food & Groceries')->actualFor(3))->toBe(5_000);
});

it('leaves flagged transactions out of every figure', function (): void {
    [$user, $year] = userWithYear();

    $housing = figuresCategory($user, 'Housing');
    $salary = figuresCategory($user, 'Salary');

    record($user, $housing, '2027-03-01', 10_000);

    // Filed under a category that records the opposite direction, so it is flagged and
    // must not be counted (TXV-05).
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $salary->id,
        'occurred_on' => '2027-03-02',
        'amount_cents' => 99_999,
    ]);

    $result = figures($year);

    expect(forCategory($result, 'Housing')->actualFor(3))->toBe(10_000)
        ->and(forCategory($result, 'Salary')->actualFor(3))->toBe(0)
        ->and($result->month(3)->actualExpenseCents)->toBe(10_000);
});

it('calls a month not started until something is in it', function (): void {
    [$user, $year] = userWithYear();

    record($user, figuresCategory($user, 'Housing'), '2027-05-01', 1_000);

    $result = figures($year);

    expect($result->month(4)->status)->toBe(MonthStatus::NotStarted)
        ->and($result->month(5)->status)->toBe(MonthStatus::InProgress);
});

it('counts a flagged transaction as starting a month even though it counts for nothing', function (): void {
    [$user, $year] = userWithYear();

    // Status asks whether the user has begun recording, which a wrong entry still answers.
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => figuresCategory($user, 'Salary')->id,
        'occurred_on' => '2027-06-01',
        'amount_cents' => 5_000,
    ]);

    $result = figures($year);

    expect($result->month(6)->status)->toBe(MonthStatus::InProgress)
        ->and($result->month(6)->actualExpenseCents)->toBe(0);
});

it('calls a month complete once it has been finished', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 3, 'completed_at' => null]);

    $result = figures($year);

    expect($result->month(2)->status)->toBe(MonthStatus::Complete)
        ->and($result->month(3)->status)->toBe(MonthStatus::NotStarted);
});

it('forecasts a finished month as exactly what happened', function (): void {
    [$user, $year] = userWithYear();

    $housing = figuresCategory($user, 'Housing');

    planMonthly($year, $housing, 70_000);
    record($user, $housing, '2027-01-05', 50_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    // Complete: the plan no longer has a say, even though it was higher.
    expect(forCategory(figures($year), 'Housing')->forecastFor(1))->toBe(50_000);
});

it('forecasts an unfinished month as the larger of plan and actual', function (): void {
    [$user, $year] = userWithYear();

    $housing = figuresCategory($user, 'Housing');

    planMonthly($year, $housing, 70_000);

    // Under the plan so far: the plan still stands (Q-04).
    record($user, $housing, '2027-01-05', 50_000);
    // Already past the plan: the real number wins.
    record($user, $housing, '2027-02-05', 90_000);

    $housingFigures = forCategory(figures($year), 'Housing');

    expect($housingFigures->forecastFor(1))->toBe(70_000)
        ->and($housingFigures->forecastFor(2))->toBe(90_000)
        ->and($housingFigures->forecastFor(3))->toBe(70_000);
});

it('forecasts spending in a category that was never planned', function (): void {
    [$user, $year] = userWithYear();

    $misc = figuresCategory($user, 'Miscellaneous');

    record($user, $misc, '2027-03-01', 12_000);

    // P = 0, so max(0, A) is the actual: unplanned spending is still spending (EDGE-03).
    $miscFigures = forCategory(figures($year), 'Miscellaneous');

    expect($miscFigures->plannedFor(3))->toBe(0)
        ->and($miscFigures->forecastFor(3))->toBe(12_000);
});

it('applies the maximum rule to income as well', function (): void {
    [$user, $year] = userWithYear();

    $salary = figuresCategory($user, 'Salary');

    planMonthly($year, $salary, 180_000);
    record($user, $salary, '2027-01-25', 200_000);

    expect(forCategory(figures($year), 'Salary')->forecastFor(1))->toBe(200_000);
});

it('totals a month across income and expense', function (): void {
    [$user, $year] = userWithYear();

    $salary = figuresCategory($user, 'Salary');
    $housing = figuresCategory($user, 'Housing');

    planMonthly($year, $salary, 180_000);
    planMonthly($year, $housing, 70_000);

    record($user, $salary, '2027-01-25', 180_000);
    record($user, $housing, '2027-01-05', 200_000);

    $month = figures($year)->month(1);

    expect($month->plannedIncomeCents)->toBe(180_000)
        ->and($month->plannedExpenseCents)->toBe(70_000)
        ->and($month->plannedNetCents())->toBe(110_000)
        ->and($month->actualIncomeCents)->toBe(180_000)
        ->and($month->actualExpenseCents)->toBe(200_000)
        // A month that spent more than it earned is a real answer, not an error.
        ->and($month->actualNetCents())->toBe(-20_000)
        ->and($month->forecastNetCents())->toBe(180_000 - 200_000);
});

it('keeps each year to itself', function (): void {
    [$user, $year] = userWithYear();

    $housing = figuresCategory($user, 'Housing');

    record($user, $housing, '2026-12-31', 11_100);
    record($user, $housing, '2027-01-01', 22_200);
    record($user, $housing, '2028-01-01', 33_300);

    // A transaction belongs to the year containing its date. The date is a calendar date,
    // so which year it falls in never depends on a clock or a timezone (§6.1).
    $housingFigures = forCategory(figures($year), 'Housing');

    expect($housingFigures->actualFor(1))->toBe(22_200)
        ->and($housingFigures->actualFor(12))->toBe(0)
        ->and($housingFigures->actualYear())->toBe(22_200);
});

it('never counts another user figures', function (): void {
    [$user, $year] = userWithYear();
    [$other] = userWithYear();

    record($user, figuresCategory($user, 'Housing'), '2027-03-01', 10_000);
    record($other, figuresCategory($other, 'Housing'), '2027-03-01', 99_999);

    expect(forCategory(figures($year), 'Housing')->actualFor(3))->toBe(10_000);
});

it('still reports a category that has been retired', function (): void {
    [$user, $year] = userWithYear();

    $holidays = figuresCategory($user, 'Holidays');

    record($user, $holidays, '2027-07-01', 80_000);
    $holidays->update(['is_active' => false]);

    // Deactivated categories leave the pickers but not the history (CAT-04).
    expect(forCategory(figures($year), 'Holidays')->actualFor(7))->toBe(80_000);
});

it('reports twelve months whatever the year holds', function (): void {
    [, $year] = userWithYear();

    $result = figures($year);

    expect($result->months)->toHaveCount(12)
        ->and($result->year)->toBe(2027)
        ->and($result->month(12)->status)->toBe(MonthStatus::NotStarted);
});

it('separates income categories from expense ones', function (): void {
    [, $year] = userWithYear();

    $result = figures($year);

    $income = $result->categoriesOfType(TransactionType::Income);
    $expense = $result->categoriesOfType(TransactionType::Expense);

    expect(collect($income)->pluck('categoryName'))->toContain('Salary')
        ->and(collect($expense)->pluck('categoryName'))->toContain('Housing')
        ->and(collect($income)->pluck('categoryName'))->not->toContain('Housing');
});
