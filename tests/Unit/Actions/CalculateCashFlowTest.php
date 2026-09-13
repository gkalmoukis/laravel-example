<?php

declare(strict_types=1);

use App\Actions\CalculateCashFlow;
use App\Actions\CreatePlanItem;
use App\Data\CashFlow;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/*
 * §7.2 is normative. Every expectation is worked out by hand from the rules.
 */

function cashCategory(User $user, string $name): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

function openWith(User $user, FinancialYear $year, int $cents): void
{
    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $cash->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => $cents]);
}

function flowSpend(User $user, Category $category, string $date, int $cents): void
{
    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function cashFlow(FinancialYear $year, string $today = '2027-06-15'): CashFlow
{
    return resolve(CalculateCashFlow::class)->handle($year, CarbonImmutable::parse($today));
}

it('starts every series from the opening position', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 500_000);

    $flow = cashFlow($year);

    expect($flow->openingBalanceCents)->toBe(500_000)
        ->and($flow->month(1)->planned->openingCents)->toBe(500_000)
        ->and($flow->month(1)->forecast->openingCents)->toBe(500_000);
});

it('carries each closing balance into the next month', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 100_000);

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => cashCategory($user, 'Salary')->id,
        'name' => 'Salary',
        'type' => 'income',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(200_000));

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => cashCategory($user, 'Housing')->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    $flow = cashFlow($year);

    // 1.000,00 opening, then 1.300,00 saved every month.
    expect($flow->month(1)->planned->netCents)->toBe(130_000)
        ->and($flow->month(1)->planned->closingCents)->toBe(230_000)
        ->and($flow->month(2)->planned->openingCents)->toBe(230_000)
        ->and($flow->month(2)->planned->closingCents)->toBe(360_000)
        ->and($flow->plannedYearEndCents())->toBe(100_000 + 12 * 130_000);
});

it('lets a balance fall below zero', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 10_000);

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => cashCategory($user, 'Housing')->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    // Spending more than there is, is exactly what the forecast has to be able to say.
    expect(cashFlow($year)->month(1)->planned->closingCents)->toBe(-60_000)
        ->and(cashFlow($year)->plannedYearEndCents())->toBe(10_000 - 12 * 70_000);
});

it('runs the actual series to the current month of the year being lived through', function (): void {
    [$user, $year] = userWithYear();

    flowSpend($user, cashCategory($user, 'Housing'), '2027-02-10', 5_000);

    // June is the current month, so the actual series runs to June even though nothing
    // was recorded after February.
    $flow = cashFlow($year, '2027-06-15');

    expect($flow->lastActualMonth)->toBe(6)
        ->and($flow->month(6)->actual)->not->toBeNull()
        ->and($flow->month(7)->actual)->toBeNull();
});

it('runs the actual series past the current month when later months have entries', function (): void {
    [$user, $year] = userWithYear();

    flowSpend($user, cashCategory($user, 'Housing'), '2027-09-10', 5_000);

    // A transaction dated ahead of today still counts for its month (EDGE-04), so the
    // series has to reach it.
    expect(cashFlow($year, '2027-06-15')->lastActualMonth)->toBe(9);
});

it('stops the actual series at the last recorded month of a past year', function (): void {
    [$user, $year] = userWithYear();

    flowSpend($user, cashCategory($user, 'Housing'), '2027-03-10', 5_000);

    $flow = cashFlow($year, '2029-01-01');

    expect($flow->lastActualMonth)->toBe(3)
        ->and($flow->month(4)->actual)->toBeNull();
});

it('has no actual series for a year with nothing in it', function (): void {
    [, $year] = userWithYear();

    $flow = cashFlow($year, '2029-01-01');

    expect($flow->lastActualMonth)->toBeNull()
        ->and($flow->month(1)->actual)->toBeNull();
});

it('has no actual series for a year that has not arrived', function (): void {
    [, $year] = userWithYear();

    expect(cashFlow($year, '2025-06-01')->lastActualMonth)->toBeNull();
});

it('builds the actual balance from what really happened', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 100_000);

    flowSpend($user, cashCategory($user, 'Salary'), '2027-01-25', 200_000);
    flowSpend($user, cashCategory($user, 'Housing'), '2027-01-05', 70_000);
    flowSpend($user, cashCategory($user, 'Housing'), '2027-02-05', 30_000);

    $flow = cashFlow($year);

    expect($flow->month(1)->actual?->incomeCents)->toBe(200_000)
        ->and($flow->month(1)->actual?->expenseCents)->toBe(70_000)
        ->and($flow->month(1)->actual?->closingCents)->toBe(230_000)
        ->and($flow->month(2)->actual?->openingCents)->toBe(230_000)
        ->and($flow->month(2)->actual?->closingCents)->toBe(200_000);
});

it('forecasts a finished month from what happened and the rest from the plan', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 0);

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => cashCategory($user, 'Housing')->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    flowSpend($user, cashCategory($user, 'Housing'), '2027-01-05', 50_000);
    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $flow = cashFlow($year);

    // January is finished, so it forecasts 500,00 spent, not the 700,00 planned.
    expect($flow->month(1)->forecast->expenseCents)->toBe(50_000)
        ->and($flow->month(2)->forecast->expenseCents)->toBe(70_000)
        ->and($flow->forecastYearEndCents())->toBe(-(50_000 + 11 * 70_000));
});

it('reports what is available to spend right now', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 100_000);

    flowSpend($user, cashCategory($user, 'Salary'), '2027-06-01', 200_000);
    flowSpend($user, cashCategory($user, 'Housing'), '2027-06-10', 30_000);
    // Dated after today, so not yet spent.
    flowSpend($user, cashCategory($user, 'Housing'), '2027-06-20', 99_999);

    expect(cashFlow($year, '2027-06-15')->currentAvailableCents)->toBe(270_000);
});

it('leaves flagged transactions out of what is available', function (): void {
    [$user, $year] = userWithYear();

    openWith($user, $year, 100_000);

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => cashCategory($user, 'Salary')->id,
        'occurred_on' => '2027-06-01',
        'amount_cents' => 50_000,
    ]);

    expect(cashFlow($year, '2027-06-15')->currentAvailableCents)->toBe(100_000);
});
