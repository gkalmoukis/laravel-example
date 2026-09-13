<?php

declare(strict_types=1);

use App\Actions\CalculateEmergencyFund;
use App\Actions\CreatePlanItem;
use App\Data\EmergencyFundStatus;
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
 * §7.6 is normative. Every expectation is worked out by hand from the rules.
 */

function fundPlan(FinancialYear $year, User $user, string $categoryName, int $cents): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function setAside(User $user, FinancialYear $year, int $month, int $cents): void
{
    $fund = $user->netWorthItems()->where('kind', NetWorthItemKind::EmergencyFund)->firstOrFail();

    NetWorthSnapshot::query()->updateOrCreate(
        [
            'net_worth_item_id' => $fund->id,
            'financial_year_id' => $year->id,
            'month' => $month,
        ],
        ['value_cents' => $cents],
    );
}

function fundGoal(User $user): Goal
{
    return $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();
}

function fundStatus(FinancialYear $year, string $today = '2027-01-15'): EmergencyFundStatus
{
    return resolve(CalculateEmergencyFund::class)->handle($year, CarbonImmutable::parse($today));
}

it('builds the target from what the user must keep paying', function (): void {
    [$user, $year] = userWithYear();

    // Housing and Utilities are essential by default; Dining Out is not.
    fundPlan($year, $user, 'Housing', 70_000);
    fundPlan($year, $user, 'Utilities', 20_000);
    fundPlan($year, $user, 'Dining Out', 30_000);

    $status = fundStatus($year);

    // 900,00 a month of essentials, six months of cover.
    expect($status->essentialMonthlyCents)->toBe(90_000)
        ->and($status->monthsOfCover)->toBe(6)
        ->and($status->targetCents)->toBe(540_000)
        ->and($status->targetIsCustom)->toBeFalse();
});

it('divides the essential year down without claiming precision it has not got', function (): void {
    [$user, $year] = userWithYear();

    // 1.000,01 a month is 12.000,12 a year, which does not divide into twelve evenly
    // once the cents are counted.
    fundPlan($year, $user, 'Housing', 100_001);

    expect(fundStatus($year)->essentialMonthlyCents)->toBe(100_001);
});

it('respects how many months of cover the user asked for', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    $user->preference->update(['emergency_fund_months' => 3]);

    $status = fundStatus($year->refresh());

    expect($status->monthsOfCover)->toBe(3)
        ->and($status->targetCents)->toBe(300_000);
});

it('lets the user name their own target instead', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    fundGoal($user)->update([
        'target_is_custom' => true,
        'target_amount_cents' => Money::fromCents(1_000_000),
    ]);

    $status = fundStatus($year);

    // Someone with an irregular income may know better than six times their essentials.
    expect($status->targetIsCustom)->toBeTrue()
        ->and($status->targetCents)->toBe(1_000_000)
        // The computed figure is still reported, so the screen can explain the choice.
        ->and($status->essentialMonthlyCents)->toBe(100_000);
});

it('ignores a custom flag with no amount behind it', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    fundGoal($user)->update(['target_is_custom' => true, 'target_amount_cents' => null]);

    expect(fundStatus($year)->targetCents)->toBe(600_000);
});

it('counts the latest figure the user gave for what is set aside', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    setAside($user, $year, 1, 100_000);
    setAside($user, $year, 2, 150_000);

    // February is the latest figure at or before the date asked about.
    expect(fundStatus($year, '2027-03-15')->currentCents)->toBe(150_000);
});

it('does not count a figure from after the date asked about', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    setAside($user, $year, 1, 100_000);
    setAside($user, $year, 6, 500_000);

    expect(fundStatus($year, '2027-03-15')->currentCents)->toBe(100_000);
});

it('counts the opening position as a starting point', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    setAside($user, $year, NetWorthSnapshot::OPENING_MONTH, 250_000);

    expect(fundStatus($year, '2027-01-15')->currentCents)->toBe(250_000);
});

it('counts the whole year once the year is behind us', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    setAside($user, $year, 12, 600_000);

    expect(fundStatus($year, '2029-01-01')->currentCents)->toBe(600_000);
});

it('counts only the opening position for a year not yet begun', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    setAside($user, $year, NetWorthSnapshot::OPENING_MONTH, 50_000);
    setAside($user, $year, 6, 500_000);

    // Asked about before the year starts, only the opening figure is real.
    expect(fundStatus($year, '2026-06-01')->currentCents)->toBe(50_000);
});

it('says when nothing has been set aside yet', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    // EF-03: the screen offers to ask rather than showing an empty bar.
    expect(fundStatus($year)->hasBeenStarted)->toBeFalse()
        ->and(fundStatus($year)->currentCents)->toBe(0);
});

it('reports what is left to go', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 200_000);

    $status = fundStatus($year);

    expect($status->targetCents)->toBe(600_000)
        ->and($status->remainingCents)->toBe(400_000)
        ->and($status->isReached)->toBeFalse();
});

it('never reports more than nothing left', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 900_000);

    $status = fundStatus($year);

    // Past the target is reached, not negative remaining.
    expect($status->remainingCents)->toBe(0)
        ->and($status->isReached)->toBeTrue()
        ->and($status->monthsToTarget)->toBe(0);
});

it('says how many months of contributions are still needed', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 300_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(100_000)]);

    // 3.000,00 short at 1.000,00 a month is three months.
    expect(fundStatus($year)->monthsToTarget)->toBe(3);
});

it('rounds a part month up, because a part month does not get there', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 350_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(100_000)]);

    // 2.500,00 short at 1.000,00 a month is three months, not two and a half.
    expect(fundStatus($year)->monthsToTarget)->toBe(3);
});

it('says the target is out of reach when nothing is going in', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    expect(fundStatus($year)->monthsToTarget)->toBeNull()
        ->and(fundStatus($year)->monthlyContributionCents)->toBe(0);
});

it('says the target is out of reach when the contribution is far too small', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(1)]);

    // A penny a month reaches 6.000,00 in fifty thousand years. Saying "not with this
    // plan" is more honest than printing the number.
    expect(fundStatus($year)->monthsToTarget)->toBeNull();
});

it('projects what will be set aside by the end of the year', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 200_000);
    setAside($user, $year, 1, 100_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(50_000)]);

    // Target is 12.000,00, well clear of the projection, so this measures the projection
    // itself: 1.000,00 now plus eleven more months of 500,00 from mid-January.
    expect(fundStatus($year, '2027-01-15')->targetCents)->toBe(1_200_000)
        ->and(fundStatus($year, '2027-01-15')->projectedYearEndCents)->toBe(650_000);
});

it('never projects past the target', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 500_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(200_000)]);

    // Money beyond the target is no longer emergency fund, it is just savings.
    expect(fundStatus($year, '2027-01-15')->projectedYearEndCents)->toBe(600_000);
});

it('projects the whole year for a year not yet begun', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(10_000)]);

    expect(fundStatus($year, '2026-06-01')->projectedYearEndCents)->toBe(120_000);
});

it('projects nothing further for a year already behind us', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 12, 200_000);
    fundGoal($user)->update(['monthly_contribution_cents' => Money::fromCents(10_000)]);

    expect(fundStatus($year, '2029-01-01')->projectedYearEndCents)->toBe(200_000);
});

it('reports progress as a pair rather than a percentage', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 150_000);

    // Full precision, rounded only for display (§7).
    expect(fundStatus($year)->progress())->toBe([150_000, 600_000]);
});

it('never reports progress beyond the whole of it', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 900_000);

    expect(fundStatus($year)->progress())->toBe([600_000, 600_000]);
});

it('ignores an archived goal', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);

    $goal = fundGoal($user);
    $goal->update(['monthly_contribution_cents' => Money::fromCents(50_000)]);
    $goal->forceFill(['archived_at' => now()])->save();

    expect(fundStatus($year)->monthlyContributionCents)->toBe(0);
});

it('ignores a retired holding', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Housing', 100_000);
    setAside($user, $year, 1, 300_000);

    $user->netWorthItems()->where('kind', NetWorthItemKind::EmergencyFund)->update(['is_active' => false]);

    expect(fundStatus($year)->currentCents)->toBe(0);
});

it('counts nothing towards the target from non-essential spending', function (): void {
    [$user, $year] = userWithYear();

    fundPlan($year, $user, 'Dining Out', 500_000);

    // Nothing essential is planned, so there is no target to speak of.
    expect(fundStatus($year)->essentialMonthlyCents)->toBe(0)
        ->and(fundStatus($year)->targetCents)->toBe(0)
        ->and(fundStatus($year)->isReached)->toBeTrue();
});
