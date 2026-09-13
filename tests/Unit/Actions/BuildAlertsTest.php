<?php

declare(strict_types=1);

use App\Actions\BuildAlerts;
use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Data\Alert;
use App\Enums\AlertType;
use App\Enums\Frequency;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/*
 * §8.19 is normative: each of the seven conditions, its severity order, and the case where
 * nothing is wrong at all.
 */

/**
 * @return list<Alert>
 */
function alertsFor(FinancialYear $year, string $today = '2027-03-15'): array
{
    return resolve(BuildAlerts::class)->handle($year, CarbonImmutable::parse($today));
}

/**
 * @return list<string>
 */
function alertTypesFor(FinancialYear $year, string $today = '2027-03-15'): array
{
    return array_map(fn (Alert $alert): string => $alert->type->value, alertsFor($year, $today));
}

function alertOf(FinancialYear $year, AlertType $type, string $today = '2027-03-15'): ?Alert
{
    foreach (alertsFor($year, $today) as $alert) {
        if ($alert->type === $type) {
            return $alert;
        }
    }

    return null;
}

function alertCategory(User $user, string $name = 'Housing'): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

function alertPlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => alertCategory($user, $categoryName)->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function alertSpend(User $user, string $categoryName, string $date, int $cents): Transaction
{
    $category = alertCategory($user, $categoryName);

    return Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function alertOpeningCash(FinancialYear $year, int $cents): void
{
    $item = $year->user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()->updateOrCreate(
        ['financial_year_id' => $year->id, 'net_worth_item_id' => $item->id, 'month' => NetWorthSnapshot::OPENING_MONTH],
        ['value_cents' => Money::fromCents($cents)],
    );
}

function alertReserve(FinancialYear $year, int $cents): void
{
    $item = $year->user->netWorthItems()->where('kind', NetWorthItemKind::EmergencyFund)->firstOrFail();

    NetWorthSnapshot::query()->updateOrCreate(
        ['financial_year_id' => $year->id, 'net_worth_item_id' => $item->id, 'month' => NetWorthSnapshot::OPENING_MONTH],
        ['value_cents' => Money::fromCents($cents)],
    );
}

it('says nothing when nothing is wrong', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);
    alertPlan($year, $user, 'Salary', 300_000, 'income');
    alertPlan($year, $user, 'Housing', 100_000);

    // Every month before March signed off, so no month is left hanging.
    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);

    alertSpend($user, 'Salary', '2027-01-25', 300_000);
    alertSpend($user, 'Housing', '2027-01-05', 100_000);
    alertSpend($user, 'Salary', '2027-02-25', 300_000);
    alertSpend($user, 'Housing', '2027-02-05', 100_000);

    expect(alertTypesFor($year))->toBe([]);
});

it('warns when the forecast goes below zero', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 10_000);
    alertPlan($year, $user, 'Housing', 100_000);

    $alert = alertOf($year, AlertType::NegativeForecastBalance);

    // January already closes below zero: 100,00 opening against 1.000,00 planned rent.
    expect($alert)->not->toBeNull()
        ->and($alert?->detail)->toBe('January')
        ->and($alert?->toArray()['explanation'])
        ->toBe('On current figures your balance goes below zero in January.')
        ->and($alert?->toArray()['actionUrl'])->toContain('/years/2027/forecast');
});

it('warns when the forecast dips into the emergency fund', function (): void {
    [$user, $year] = userWithYear();

    // B0 is cash plus the reserve: 9.000,00. Twelve months at 500,00 leaves 3.000,00 by
    // December, which is below the 4.000,00 set aside but never below zero (§7.2, §7.6).
    alertOpeningCash($year, 500_000);
    alertReserve($year, 400_000);
    alertPlan($year, $user, 'Housing', 50_000);

    $alert = alertOf($year, AlertType::ForecastBelowEmergencyFund);

    expect($alert)->not->toBeNull()
        ->and($alert?->detail)->toBe('December')
        ->and($alert?->toArray()['title'])
        ->toBe('You would have to dip into your emergency fund');
});

it('ignores a dip that is already behind the user', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 500_000);
    alertReserve($year, 400_000);
    alertPlan($year, $user, 'Housing', 50_000);

    // The same year, read after it ended: there is nothing left to do about it.
    expect(alertTypesFor($year, '2028-01-15'))
        ->not->toContain(AlertType::ForecastBelowEmergencyFund->value);
});

it('measures a year not yet begun across all twelve months', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 500_000);
    alertReserve($year, 400_000);
    alertPlan($year, $user, 'Housing', 50_000);

    expect(alertTypesFor($year, '2026-06-01'))
        ->toContain(AlertType::ForecastBelowEmergencyFund->value);
});

it('counts transactions that cannot be used as they stand', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    // Filed under an income category while recording an expense (TXV-05).
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => alertCategory($user, 'Salary')->id,
        'occurred_on' => '2027-02-05',
        'amount_cents' => 5_000,
    ]);

    $issues = alertOf($year, AlertType::TransactionsWithIssues);
    $mismatch = alertOf($year, AlertType::CategoryTypeMismatch);

    expect($issues?->detail)->toBe('1 transaction')
        ->and($issues?->toArray()['explanation'])
        ->toBe('1 transaction will not be counted in your reports until they are fixed.')
        ->and($issues?->toArray()['actionUrl'])->toContain('issues=1')
        ->and($mismatch?->detail)->toBe('1 transaction');
});

it('reports a transaction with no plan for its year whichever year is selected', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    alertSpend($user, 'Housing', '2029-04-05', 5_000);
    alertSpend($user, 'Housing', '2029-05-05', 5_000);

    $alert = alertOf($year, AlertType::TransactionsWithIssues);

    expect($alert?->detail)->toBe('2 transactions')
        // No category is misfiled, so only the one alert (ALRT-01, not ALRT-02).
        ->and(alertOf($year, AlertType::CategoryTypeMismatch))->toBeNull();
});

it('leaves another year flagged transactions to that year', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    // 2028 has a plan of its own, so this is flagged but belongs to that year's list.
    resolve(CreateFinancialYear::class)->handle($user, 2028);

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => alertCategory($user, 'Salary')->id,
        'occurred_on' => '2028-02-05',
        'amount_cents' => 5_000,
    ]);

    expect(alertOf($year, AlertType::TransactionsWithIssues))->toBeNull()
        ->and(alertOf($year, AlertType::CategoryTypeMismatch))->toBeNull();
});

it('names a category that has gone over budget this month', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);
    alertPlan($year, $user, 'Housing', 50_000);
    alertSpend($user, 'Housing', '2027-03-05', 90_000);

    $alert = alertOf($year, AlertType::BudgetOverrun);

    expect($alert?->detail)->toBe('Housing')
        ->and($alert?->toArray()['explanation'])->toBe('Housing has spent more than it planned to.')
        ->and($alert?->toArray()['actionUrl'])->toContain('/years/2027/comparison');
});

it('names a category that went over in the last month signed off', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);
    alertPlan($year, $user, 'Housing', 50_000);
    alertSpend($user, 'Housing', '2027-01-05', 90_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    // Read in a year already over, so there is no current month to check — only the
    // latest one signed off, which is January.
    expect(alertOf($year, AlertType::BudgetOverrun, '2028-02-01')?->detail)->toBe('Housing');
});

it('points at the first month left unfinished', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $alert = alertOf($year, AlertType::IncompleteMonth);

    expect($alert?->detail)->toBe('February')
        ->and($alert?->toArray()['actionUrl'])->toContain('/years/2027/months/2');
});

it('asks nothing of a year that has not started', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    expect(alertTypesFor($year, '2026-06-01'))->not->toContain(AlertType::IncompleteMonth->value);
});

it('treats every month of a year already over as one that should be finished', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    expect(alertOf($year, AlertType::IncompleteMonth, '2028-06-01')?->detail)->toBe('January');
});

it('names a goal that will miss its date', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 1_000_000);

    Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'New bike',
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => Money::zero(),
        'target_date' => '2027-06-30',
    ]);

    $alert = alertOf($year, AlertType::GoalOffTrack);

    expect($alert?->detail)->toBe('New bike')
        ->and($alert?->toArray()['explanation'])
        ->toBe('New bike will not reach its target by the date you set.')
        ->and($alert?->toArray()['actionUrl'])->toContain('/goals');
});

it('puts the loudest alert first', function (): void {
    [$user, $year] = userWithYear();

    alertOpeningCash($year, 10_000);
    alertReserve($year, 400_000);
    alertPlan($year, $user, 'Housing', 100_000);
    alertSpend($user, 'Housing', '2027-03-05', 900_000);

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => alertCategory($user, 'Salary')->id,
        'occurred_on' => '2027-02-05',
        'amount_cents' => 5_000,
    ]);

    Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'New bike',
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => Money::zero(),
        'target_date' => '2027-06-30',
    ]);

    // §8.19: 07, 06, 01/02, 03, 04, 05.
    expect(alertTypesFor($year))->toBe([
        AlertType::NegativeForecastBalance->value,
        AlertType::ForecastBelowEmergencyFund->value,
        AlertType::TransactionsWithIssues->value,
        AlertType::CategoryTypeMismatch->value,
        AlertType::BudgetOverrun->value,
        AlertType::IncompleteMonth->value,
        AlertType::GoalOffTrack->value,
    ]);
});

it('gives every alert type a title, a sentence, an action and a rank', function (AlertType $type): void {
    expect($type->title())->not->toBe('')
        ->and($type->explanation('March'))->toContain('March')
        ->and($type->actionLabel())->not->toBe('')
        ->and($type->severity())->toBeGreaterThanOrEqual(0);
})->with(AlertType::cases());

it('ranks the seven types in the order the specification sets', function (): void {
    $ranks = array_map(fn (AlertType $type): int => $type->severity(), AlertType::cases());

    expect($ranks)->toBe([0, 1, 2, 3, 4, 5, 6]);
});
