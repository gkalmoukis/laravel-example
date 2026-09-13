<?php

declare(strict_types=1);

use App\Actions\CalculateGoalProgress;
use App\Actions\CreatePlanItem;
use App\Data\GoalProgress;
use App\Enums\Frequency;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/*
 * §7.7 is normative. Every expectation is worked out by hand from the rules.
 */

function goalFor(User $user, GoalType $type, array $attributes = []): Goal
{
    return Goal::factory()->for($user)->create([
        'type' => $type,
        'name' => $type->value,
        ...$attributes,
    ]);
}

function goalProgress(Goal $goal, ?FinancialYear $year = null, string $today = '2027-03-15'): GoalProgress
{
    return resolve(CalculateGoalProgress::class)->forGoal($goal, $year, CarbonImmutable::parse($today));
}

it('reports an ordinary goal from what the user has told it', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Purchase, [
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::fromCents(200_000),
    ]);

    $progress = goalProgress($goal);

    expect($progress->currentCents)->toBe(200_000)
        ->and($progress->remainingCents)->toBe(300_000)
        ->and($progress->isReached)->toBeFalse()
        // Nobody derives this one, so the screen lets the user edit it (GOAL-03).
        ->and($progress->isTracked)->toBeFalse();
});

it('never reports more than nothing left', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Purchase, [
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::fromCents(150_000),
    ]);

    expect(goalProgress($goal)->remainingCents)->toBe(0)
        ->and(goalProgress($goal)->isReached)->toBeTrue()
        ->and(goalProgress($goal)->progress())->toBe([100_000, 100_000]);
});

it('reads the emergency fund from what is actually set aside', function (): void {
    [$user, $year] = userWithYear();

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(100_000));

    $fund = $user->netWorthItems()->where('kind', NetWorthItemKind::EmergencyFund)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $fund->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 250_000]);

    $goal = $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();

    $progress = goalProgress($goal, $year);

    // Both sides come from elsewhere: 6 × 1.000,00 essentials, 2.500,00 set aside.
    expect($progress->targetCents)->toBe(600_000)
        ->and($progress->currentCents)->toBe(250_000)
        ->and($progress->isTracked)->toBeTrue();
});

it('has nothing to say about the emergency fund without a year', function (): void {
    $user = planningUser();

    $goal = $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();

    expect(goalProgress($goal)->currentCents)->toBe(0)
        ->and(goalProgress($goal)->isTracked)->toBeTrue();
});

it('reads a year end balance from the forecast', function (): void {
    [$user, $year] = userWithYear();

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Salary')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Salary',
        'type' => 'income',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(100_000));

    $goal = goalFor($user, GoalType::YearEndBalance, [
        'financial_year_id' => $year->id,
        'target_amount_cents' => Money::fromCents(1_000_000),
    ]);

    // Twelve months of 1.000,00 with nothing going out.
    expect(goalProgress($goal, $year)->currentCents)->toBe(1_200_000)
        ->and(goalProgress($goal, $year)->isTracked)->toBeTrue();
});

it('falls back to the year on screen when the goal names none', function (): void {
    [$user, $year] = userWithYear();

    $goal = goalFor($user, GoalType::YearEndBalance, [
        'financial_year_id' => null,
        'target_amount_cents' => Money::fromCents(100_000),
    ]);

    expect(goalProgress($goal, $year)->currentCents)->toBe(0);
});

it('has nothing to say about a year end balance with no year at all', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::YearEndBalance, [
        'financial_year_id' => null,
        'target_amount_cents' => Money::fromCents(100_000),
    ]);

    expect(goalProgress($goal)->currentCents)->toBe(0)
        ->and(goalProgress($goal)->estimatedCompletion)->toBeNull();
});

it('says a year end balance arrives on the last day of its year', function (): void {
    [$user, $year] = userWithYear();

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Salary')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Salary',
        'type' => 'income',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(100_000));

    $goal = goalFor($user, GoalType::YearEndBalance, [
        'financial_year_id' => $year->id,
        'target_amount_cents' => Money::fromCents(500_000),
    ]);

    // A year end balance is not saved towards; it is what the year leaves behind.
    expect(goalProgress($goal, $year)->estimatedCompletion?->toDateString())->toBe('2027-12-31');
});

it('says a year end balance that falls short arrives never', function (): void {
    [$user, $year] = userWithYear();

    $goal = goalFor($user, GoalType::YearEndBalance, [
        'financial_year_id' => $year->id,
        'target_amount_cents' => Money::fromCents(5_000_000),
    ]);

    expect(goalProgress($goal, $year)->estimatedCompletion)->toBeNull();
});

it('counts the months of contributions still needed', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::fromCents(200_000),
        'monthly_contribution_cents' => Money::fromCents(100_000),
    ]);

    // 3.000,00 short at 1.000,00 a month is three months from March.
    expect(goalProgress($goal, null, '2027-03-15')->estimatedCompletion?->toDateString())
        ->toBe('2027-06-01');
});

it('rounds a part month up, because a part month does not get there', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(250_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => Money::fromCents(100_000),
    ]);

    // 2.500,00 at 1.000,00 a month is three months, not two and a half.
    expect(goalProgress($goal, null, '2027-03-15')->estimatedCompletion?->toDateString())
        ->toBe('2027-06-01');
});

it('says a goal already met arrives now', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::fromCents(100_000),
    ]);

    expect(goalProgress($goal, null, '2027-03-15')->estimatedCompletion?->toDateString())
        ->toBe('2027-03-01');
});

it('says a goal with nothing going in arrives never', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => null,
    ]);

    expect(goalProgress($goal)->estimatedCompletion)->toBeNull();
});

it('says a goal funded a penny at a time arrives never', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(5_000_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => Money::fromCents(1),
    ]);

    // Printing a date four thousand years out would be worse than saying no.
    expect(goalProgress($goal)->estimatedCompletion)->toBeNull();
});

it('is not off track without a date to miss', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::zero(),
        'target_date' => null,
    ]);

    expect(goalProgress($goal)->isOffTrack)->toBeFalse();
});

it('is off track when the date will be missed', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => Money::fromCents(100_000),
        'target_date' => '2027-05-01',
    ]);

    // Five months of saving from March lands in August, past May.
    expect(goalProgress($goal, null, '2027-03-15')->isOffTrack)->toBeTrue();
});

it('is on track when the date will be met', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(200_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => Money::fromCents(100_000),
        'target_date' => '2027-12-01',
    ]);

    expect(goalProgress($goal, null, '2027-03-15')->isOffTrack)->toBeFalse();
});

it('is off track when there is a date and nothing going in', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::zero(),
        'monthly_contribution_cents' => null,
        'target_date' => '2027-12-01',
    ]);

    // Nothing will change between now and December, so it is already late.
    expect(goalProgress($goal)->isOffTrack)->toBeTrue();
});

it('is not off track once the goal is met, whatever the date said', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::fromCents(100_000),
        'target_date' => '2020-01-01',
    ]);

    expect(goalProgress($goal)->isOffTrack)->toBeFalse();
});

it('is off track when a year end balance will not reach its date', function (): void {
    [$user, $year] = userWithYear();

    $goal = goalFor($user, GoalType::YearEndBalance, [
        'financial_year_id' => $year->id,
        'target_amount_cents' => Money::fromCents(5_000_000),
        'target_date' => '2027-12-31',
    ]);

    // Nothing planned, so the forecast never gets there — and a year end balance is not
    // rescued by a monthly contribution.
    expect(goalProgress($goal, $year)->isOffTrack)->toBeTrue();
});

it('lists every goal the user is still working on', function (): void {
    $user = planningUser();

    goalFor($user, GoalType::Investment, ['name' => 'Shares']);
    $archived = goalFor($user, GoalType::Purchase, ['name' => 'Sofa']);
    $archived->forceFill(['archived_at' => now()])->save();

    $all = resolve(CalculateGoalProgress::class)->handle($user, null, CarbonImmutable::parse('2027-03-15'));

    $names = collect($all)->pluck('name');

    // The emergency fund is provisioned for everyone, so it is always in the list.
    expect($names)->toContain('Shares')
        ->and($names)->not->toContain('Sofa');
});

it('reports progress as a pair rather than a percentage', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Investment, [
        'target_amount_cents' => Money::fromCents(300_000),
        'current_amount_cents' => Money::fromCents(100_000),
    ]);

    expect(goalProgress($goal)->progress())->toBe([100_000, 300_000]);
});

it('handles a goal with no target at all', function (): void {
    $user = planningUser();

    $goal = goalFor($user, GoalType::Other, [
        'target_amount_cents' => null,
        'current_amount_cents' => Money::zero(),
    ]);

    // Nothing to reach means nothing outstanding, rather than a division by zero.
    expect(goalProgress($goal)->targetCents)->toBe(0)
        ->and(goalProgress($goal)->remainingCents)->toBe(0)
        ->and(goalProgress($goal)->isReached)->toBeTrue();
});
