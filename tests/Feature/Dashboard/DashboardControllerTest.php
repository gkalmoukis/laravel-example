<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Enums\AlertType;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

/*
 * The home screen shows the same figures as the screens it links to, because it is built
 * from the same Actions rather than computing them again (DASH-01 … DASH-06).
 */

function dashCategory(User $user, string $name): int
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail()->id;
}

function dashPlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => dashCategory($user, $categoryName),
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function dashRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function dashSnapshot(FinancialYear $year, NetWorthItemKind $kind, int $month, int $cents): void
{
    $item = $year->user->netWorthItems()->where('kind', $kind)->firstOrFail();

    NetWorthSnapshot::query()->updateOrCreate(
        ['financial_year_id' => $year->id, 'net_worth_item_id' => $item->id, 'month' => $month],
        ['value_cents' => Money::fromCents($cents)],
    );
}

/**
 * @return array<string, mixed>
 */
function dashProps(User $user): array
{
    $props = null;

    test()->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

    return $props;
}

it('sends a user with no plan to make one', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirectToRoute('financial-years.create');
});

it('shows the six primary figures for the selected year', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2027-03-15 09:00:00');

    dashSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 250_000);
    dashSnapshot($year, NetWorthItemKind::EmergencyFund, NetWorthSnapshot::OPENING_MONTH, 150_000);

    dashPlan($year, $user, 'Salary', 300_000, 'income');
    dashPlan($year, $user, 'Housing', 100_000);

    dashRecord($user, 'Salary', '2027-01-25', 300_000);
    dashRecord($user, 'Housing', '2027-01-05', 100_000);

    $props = dashProps($user);

    expect($props['year'])->toBe(2027)
        // B0 is cash plus the reserve (§7.2): 4.000,00, plus January's 2.000,00 net.
        ->and($props['currentAvailableCents'])->toBe(600_000)
        ->and($props['income']['actualCents'])->toBe(300_000)
        ->and($props['income']['plannedCents'])->toBe(3_600_000)
        ->and($props['expenses']['actualCents'])->toBe(100_000)
        ->and($props['expenses']['plannedCents'])->toBe(1_200_000)
        ->and($props['savings']['actualCents'])->toBe(200_000)
        ->and($props['savings']['plannedCents'])->toBe(2_400_000)
        ->and($props['emergencyFund']['currentCents'])->toBe(150_000);
});

it('carries the year end forecast and whether there is a baseline to compare it with', function (): void {
    [$user, $year] = userWithYear();

    dashSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 500_000);
    dashPlan($year, $user, 'Housing', 100_000);

    $props = dashProps($user);

    // 5.000,00 opening less twelve months at 1.000,00 leaves it 7.000,00 in the red.
    expect($props['yearEnd']['forecastCents'])->toBe(-700_000)
        ->and($props['yearEnd']['hasBaseline'])->toBeFalse()
        ->and($props['yearEnd']['deviationCents'])->toBe(0);
});

it('counts the months signed off and what is still to do', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);

    expect(dashProps($user)['completedMonths'])->toBe(2);
});

it('counts transactions that need a look, and says nothing when there are none', function (): void {
    [$user, $year] = userWithYear();

    expect(dashProps($user)['transactionIssues'])->toBe(0);

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => dashCategory($user, 'Salary'),
        'occurred_on' => '2027-02-05',
        'amount_cents' => 5_000,
    ]);

    expect(dashProps($user)['transactionIssues'])->toBe(1);
});

it('reports net worth and how it has moved', function (): void {
    [$user, $year] = userWithYear();

    dashSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 100_000);
    dashSnapshot($year, NetWorthItemKind::Cash, 1, 180_000);

    $props = dashProps($user);

    expect($props['netWorth']['currentCents'])->toBe(180_000)
        ->and($props['netWorth']['changeCents'])->toBe(80_000);
});

it('carries the alerts so the panel can show them', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2027-03-15 09:00:00');

    dashSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 10_000);
    dashPlan($year, $user, 'Housing', 100_000);

    $alerts = dashProps($user)['alerts'];

    expect($alerts)->not->toBe([])
        ->and($alerts[0]['type'])->toBe(AlertType::NegativeForecastBalance->value)
        ->and($alerts[0]['title'])->not->toBe('')
        ->and($alerts[0]['actionUrl'])->toContain('/years/2027/forecast');
});

it('follows the year the user has selected', function (): void {
    $user = planningUser();

    resolve(CreateFinancialYear::class)->handle($user, 2026);
    resolve(CreateFinancialYear::class)->handle($user, 2027);

    $this->travelTo('2027-06-15 09:00:00');

    // The year they are living in, when they have planned it (YEAR-07).
    expect(dashProps($user)['year'])->toBe(2027);

    $this->actingAs($user)->get(route('transactions.index', ['year' => 2026]));

    expect(dashProps($user)['year'])->toBe(2026);
});

it('shows another user nothing of this one', function (): void {
    [$user, $year] = userWithYear();

    dashSnapshot($year, NetWorthItemKind::Cash, NetWorthSnapshot::OPENING_MONTH, 900_000);

    $intruder = planningUser();
    resolve(CreateFinancialYear::class)->handle($intruder, 2027);

    expect(dashProps($intruder)['netWorth']['currentCents'])->toBe(0);
});
