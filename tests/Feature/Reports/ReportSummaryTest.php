<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Actions\ProvisionUserDefaults;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

function summaryPlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function summaryOpenWith(User $user, FinancialYear $year, int $cents): void
{
    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $cash->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => $cents]);
}

function summarySpend(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

it('gathers the year into one screen', function (): void {
    [$user, $year] = userWithYear();

    summaryOpenWith($user, $year, 100_000);
    summaryPlan($year, $user, 'Salary', 200_000, 'income');
    summaryPlan($year, $user, 'Housing', 70_000);

    $this->actingAs($user)
        ->get(route('reports.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->component('reports/summary')
            ->where('year', 2027)
            // Twelve months of each, straight from CalculateAnnualSummary.
            ->where('income.plannedCents', 2_400_000)
            ->where('expenses.plannedCents', 840_000)
            ->where('savings.plannedCents', 1_560_000)
            ->where('balance.openingCents', 100_000)
            ->where('balance.plannedYearEndCents', 1_660_000)
            ->has('alerts'));
});

it('reports the same figures the tabs it links to report', function (): void {
    [$user, $year] = userWithYear();

    summaryOpenWith($user, $year, 250_000);
    summaryPlan($year, $user, 'Salary', 180_000, 'income');
    summaryPlan($year, $user, 'Housing', 60_000);

    // Composed from the same Actions, so the summary can never disagree with the screen
    // it sends the user to (FC-01, CF-01).
    $forecast = $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->viewData('page')['props'];

    $cashFlow = $this->actingAs($user)
        ->get(route('cash-flow.index', ['year' => 2027]))
        ->viewData('page')['props'];

    $this->actingAs($user)
        ->get(route('reports.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('income.forecastCents', $forecast['summary']['incomeCents'])
            ->where('expenses.forecastCents', $forecast['summary']['expenseCents'])
            ->where('balance.availableCents', $cashFlow['currentAvailableCents'])
            ->where('balance.forecastYearEndCents', $cashFlow['forecastYearEndCents']));
});

it('counts only the months that were signed off', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create([
        'month' => 1,
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page->where('completedMonths', 1));
});

it('carries the alerts the year is raising', function (): void {
    [$user, $year] = userWithYear();

    // A plan that spends more than it earns drives the balance below zero, which is the
    // alert the summary exists to put in front of the user (ALRT-01).
    summaryPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->get(route('reports.index', ['year' => 2027]))
        ->assertInertia(function ($page): void {
            $alerts = $page->toArray()['props']['alerts'];

            expect($alerts)->not->toBe([]);

            foreach ($alerts as $alert) {
                expect($alert)->toHaveKeys([
                    'type', 'title', 'explanation', 'actionLabel', 'actionUrl', 'severity',
                ]);
            }
        });
});

it("hides another user's year behind a 404", function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    resolve(ProvisionUserDefaults::class)->handle($owner);
    resolve(CreateFinancialYear::class)->handle($owner, 2027);

    $this->actingAs($stranger)
        ->get(route('reports.index', ['year' => 2027]))
        ->assertNotFound();
});

it('needs a signed-in user', function (): void {
    $this->get(route('reports.index', ['year' => 2027]))->assertRedirect(route('login'));
});
