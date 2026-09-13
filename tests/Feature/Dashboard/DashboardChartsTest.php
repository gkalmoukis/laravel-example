<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

/*
 * Each chart is its own deferred prop, so a slow one never holds up the rest (DASH-04,
 * FE-08). The figures themselves belong to the Actions that own them; what is asserted
 * here is that each prop is withheld from the first response and resolves on request.
 */

function chartsPlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function chartsRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function chartsSnapshot(FinancialYear $year, NetWorthItemKind $kind, int $month, int $cents): void
{
    $item = $year->user->netWorthItems()->where('kind', $kind)->firstOrFail();

    NetWorthSnapshot::query()->updateOrCreate(
        ['financial_year_id' => $year->id, 'net_worth_item_id' => $item->id, 'month' => $month],
        ['value_cents' => Money::fromCents($cents)],
    );
}

/**
 * Asks for one deferred prop the way the browser does, and returns it.
 */
function chartProp(User $user, string $prop): mixed
{
    $response = test()->actingAs($user)
        ->get(route('dashboard'), [
            'X-Inertia' => 'true',
            // The asset version has to match or Inertia asks the browser to reload (409).
            'X-Inertia-Version' => resolve(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'dashboard',
            'X-Inertia-Partial-Data' => $prop,
        ])
        ->assertOk();

    // A partial reload answers with JSON rather than a rendered page, so the prop is read
    // from the response body rather than through assertInertia.
    return $response->json('props.'.$prop);
}

it('withholds every chart from the first response', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(function ($page): void {
            $props = $page->toArray()['props'];

            foreach ([
                'closingBalance',
                'expensesByCategory',
                'incomeAndExpenses',
                'netWorthTrend',
                'goalsProgress',
            ] as $chart) {
                expect($props)->not->toHaveKey($chart);
            }

            // The figures the user came for are there from the first paint.
            expect($props)->toHaveKey('currentAvailableCents');
        });
});

it('resolves the closing balance for all twelve months', function (): void {
    [$user, $year] = userWithYear();

    chartsSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 500_000);
    chartsPlan($year, $user, 'Housing', 100_000);

    $points = chartProp($user, 'closingBalance');

    expect($points)->toHaveCount(12)
        ->and($points[0]['month'])->toBe(1)
        // 5.000,00 opening less the first month's 1.000,00.
        ->and($points[0]['plannedCents'])->toBe(400_000)
        ->and($points[11]['forecastCents'])->toBe(-700_000)
        // Nothing recorded, so there is no actual line to draw.
        ->and($points[0]['actualCents'])->toBeNull();
});

it('resolves expenses by category for the month on show', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2027-03-15 09:00:00');

    chartsPlan($year, $user, 'Housing', 100_000);
    chartsRecord($user, 'Housing', '2027-03-05', 120_000);

    $data = chartProp($user, 'expensesByCategory');

    expect($data['month'])->toBe(3)
        ->and($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['categoryName'])->toBe('Housing')
        ->and($data['rows'][0]['plannedCents'])->toBe(100_000)
        ->and($data['rows'][0]['actualCents'])->toBe(120_000);
});

it('shows the latest month signed off rather than the one being lived through', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2027-06-15 09:00:00');

    chartsPlan($year, $user, 'Housing', 100_000);
    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);

    expect(chartProp($user, 'expensesByCategory')['month'])->toBe(2);
});

it('falls back to the first month of a year nobody is living in', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2029-06-15 09:00:00');

    chartsPlan($year, $user, 'Housing', 100_000);

    expect(chartProp($user, 'expensesByCategory')['month'])->toBe(1);
});

it('says whether each month is a fact or a forecast', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2027-03-15 09:00:00');

    chartsPlan($year, $user, 'Salary', 300_000, 'income');
    chartsPlan($year, $user, 'Housing', 100_000);

    chartsRecord($user, 'Salary', '2027-01-25', 250_000);
    chartsRecord($user, 'Housing', '2027-01-05', 90_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $points = chartProp($user, 'incomeAndExpenses');

    expect($points)->toHaveCount(12)
        // January is signed off, so it reports what really happened.
        ->and($points[0]['isActual'])->toBeTrue()
        ->and($points[0]['incomeCents'])->toBe(250_000)
        ->and($points[0]['expenseCents'])->toBe(90_000)
        // Everything after it is still the plan.
        ->and($points[5]['isActual'])->toBeFalse()
        ->and($points[5]['incomeCents'])->toBe(300_000);
});

it('resolves the net worth trend from the opening position', function (): void {
    [$user, $year] = userWithYear();

    chartsSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 100_000);
    chartsSnapshot($year, NetWorthItemKind::Cash, 2, 160_000);

    $points = chartProp($user, 'netWorthTrend');

    expect($points)->toHaveCount(13)
        ->and($points[0]['month'])->toBe(0)
        ->and($points[0]['netCents'])->toBe(100_000)
        ->and($points[2]['netCents'])->toBe(160_000)
        ->and($points[2]['isRecorded'])->toBeTrue()
        // Beyond February the last known figure is carried forward (NW-04).
        ->and($points[3]['netCents'])->toBe(160_000)
        ->and($points[3]['isRecorded'])->toBeFalse();
});

it('resolves a bar for each goal', function (): void {
    [$user, $year] = userWithYear();

    Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'New bike',
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::fromCents(200_000),
        'monthly_contribution_cents' => Money::zero(),
    ]);

    $goals = collect(chartProp($user, 'goalsProgress'))->keyBy('name');

    // Every account is provisioned with an emergency fund goal, so the new one joins it.
    expect($goals)->toHaveCount(2)
        ->and($goals['New bike']['currentCents'])->toBe(200_000)
        ->and($goals['New bike']['targetCents'])->toBe(500_000)
        ->and($goals['New bike']['isReached'])->toBeFalse()
        ->and($goals)->toHaveKey('Emergency Fund');
});

it('resolves empty charts rather than failing when there is nothing to draw', function (): void {
    [$user] = userWithYear();

    expect(chartProp($user, 'expensesByCategory')['rows'])->toBe([])
        ->and(chartProp($user, 'closingBalance'))->toHaveCount(12);
});
