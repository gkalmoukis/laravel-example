<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

function cashFlowPlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function cashFlowOpenWith(User $user, FinancialYear $year, int $cents): void
{
    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $cash->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => $cents]);
}

function cashFlowRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

it('shows twelve months of balances', function (): void {
    [$user, $year] = userWithYear();

    cashFlowOpenWith($user, $year, 100_000);

    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->component('cash-flow/index')
            ->where('year', 2027)
            ->where('openingBalanceCents', 100_000)
            ->has('months', 12)
            ->where('months.0.month', 1)
            ->where('months.11.month', 12));
});

it('carries the closing balance into the next month', function (): void {
    [$user, $year] = userWithYear();

    cashFlowOpenWith($user, $year, 100_000);
    cashFlowPlan($year, $user, 'Salary', 200_000, 'income');
    cashFlowPlan($year, $user, 'Housing', 70_000);

    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.planned.openingCents', 100_000)
            ->where('months.0.planned.netCents', 130_000)
            ->where('months.0.planned.closingCents', 230_000)
            ->where('months.1.planned.openingCents', 230_000)
            ->where('plannedYearEndCents', 100_000 + 12 * 130_000));
});

it('reports no actual line for a month not yet reached', function (): void {
    [$user, $year] = userWithYear();

    cashFlowOpenWith($user, $year, 50_000);

    // 2027 is ahead of the frozen clock, so nothing has been reached at all.
    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('lastActualMonth', null)
            ->where('months.0.actual', null));
});

it('reports the actual line for months the user has reached', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-03-15');

    cashFlowOpenWith($user, $year, 100_000);
    cashFlowRecord($user, 'Housing', '2026-01-05', 30_000);

    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2026]))
        ->assertInertia(fn ($page) => $page
            ->where('lastActualMonth', 3)
            ->where('months.0.actual.expenseCents', 30_000)
            ->where('months.0.actual.closingCents', 70_000)
            ->where('months.3.actual', null));
});

it('lets the balance fall below zero', function (): void {
    [$user, $year] = userWithYear();

    cashFlowOpenWith($user, $year, 10_000);
    cashFlowPlan($year, $user, 'Housing', 70_000);

    // Running out of money is the most important thing this screen can show.
    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.planned.closingCents', -60_000)
            ->where('plannedYearEndCents', 10_000 - 12 * 70_000));
});

it('reports what is available right now', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-06-15');

    cashFlowOpenWith($user, $year, 100_000);
    cashFlowRecord($user, 'Salary', '2026-06-01', 200_000);
    // Dated after today, so not yet spent.
    cashFlowRecord($user, 'Housing', '2026-06-20', 99_999);

    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2026]))
        ->assertInertia(fn ($page) => $page->where('currentAvailableCents', 300_000));
});

it('forecasts from what happened once a month is signed off', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-03-15');

    cashFlowOpenWith($user, $year, 0);
    cashFlowPlan($year, $user, 'Housing', 70_000);
    cashFlowRecord($user, 'Housing', '2026-01-05', 50_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2026]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.forecast.expenseCents', 50_000)
            ->where('months.1.forecast.expenseCents', 70_000)
            ->where('forecastYearEndCents', -(50_000 + 11 * 70_000)));
});

it('reports another user year as missing', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $this->actingAs($intruder)
        ->get(route('cash-flow.index', ['year' => 2027]))
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2027)->exists())->toBeTrue();
});
