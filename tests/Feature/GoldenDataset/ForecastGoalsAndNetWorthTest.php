<?php

declare(strict_types=1);

use App\Actions\CalculateEmergencyFund;
use App\Actions\CalculateGoalProgress;
use App\Actions\CalculateNetWorth;
use App\Data\GoalProgress;
use App\Data\NetWorthHolding;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use Carbon\CarbonImmutable;
use Tests\Fixtures\GoldenYear;

/*
 * TST-02, second half: the same fully specified year, checked against the forecast,
 * emergency fund, goals and net worth figures it implies.
 *
 * "Today" is pinned to 15 March 2027, inside the year and inside its in-progress month —
 * the same instant the first half uses, so the two halves describe one moment.
 */

function m5Today(): CarbonImmutable
{
    return CarbonImmutable::parse('2027-03-15');
}

function goldenGoal(array $progress, string $name): GoalProgress
{
    foreach ($progress as $goal) {
        if ($goal->name === $name) {
            return $goal;
        }
    }

    throw new RuntimeException('No goal called '.$name);
}

it('builds the emergency fund target from essentials alone', function (): void {
    [, $year] = GoldenYear::build();

    $status = resolve(CalculateEmergencyFund::class)->handle($year, m5Today());

    // Housing and Food are essential; the holiday is not, so it is left out.
    expect($status->essentialMonthlyCents)->toBe(GoldenYear::ESSENTIAL_MONTHLY)
        ->and($status->monthsOfCover)->toBe(6)
        ->and($status->targetCents)->toBe(GoldenYear::EMERGENCY_TARGET)
        ->and($status->targetIsCustom)->toBeFalse();
});

it('reads the emergency fund from the latest figure given', function (): void {
    [, $year] = GoldenYear::build();

    $status = resolve(CalculateEmergencyFund::class)->handle($year, m5Today());

    expect($status->currentCents)->toBe(GoldenYear::MARCH_FUND)
        ->and($status->remainingCents)->toBe(GoldenYear::EMERGENCY_TARGET - GoldenYear::MARCH_FUND)
        ->and($status->isReached)->toBeFalse()
        ->and($status->hasBeenStarted)->toBeTrue();
});

it('says when the emergency fund gets there and where it ends the year', function (): void {
    [, $year] = GoldenYear::build();

    $status = resolve(CalculateEmergencyFund::class)->handle($year, m5Today());

    expect($status->monthlyContributionCents)->toBe(GoldenYear::EMERGENCY_CONTRIBUTION)
        ->and($status->monthsToTarget)->toBe(GoldenYear::EMERGENCY_MONTHS_TO_TARGET)
        // Nine months left in the year, still short of the target.
        ->and($status->projectedYearEndCents)->toBe(GoldenYear::EMERGENCY_PROJECTED_YEAR_END);
});

it('opens the year with everything owned and owed', function (): void {
    [, $year] = GoldenYear::build();

    $opening = resolve(CalculateNetWorth::class)->handle($year)->month(0);

    expect($opening->assetsCents)
        ->toBe(GoldenYear::OPENING_BALANCE + GoldenYear::OPENING_FUND + GoldenYear::OPENING_INVESTMENT)
        ->and($opening->debtsCents)->toBe(GoldenYear::OPENING_DEBT)
        ->and($opening->netCents())->toBe(GoldenYear::OPENING_NET_WORTH);
});

it('carries the opening figures through a month with nothing recorded', function (): void {
    [, $year] = GoldenYear::build();

    $position = resolve(CalculateNetWorth::class)->handle($year);

    // Nothing was recorded in February, so every holding that has a value keeps it and
    // says so. Provisioning opens all five at zero, so all five carry (§7.8, NW-04).
    expect($position->month(2)->netCents())->toBe(GoldenYear::OPENING_NET_WORTH)
        ->and(collect($position->month(2)->holdings)->pluck('isCarriedForward')->unique()->all())
        ->toBe([true])
        ->and($position->month(2)->holdings)->toHaveCount(5);
});

it('takes the March figures the user gave', function (): void {
    [, $year] = GoldenYear::build();

    $march = resolve(CalculateNetWorth::class)->handle($year)->month(3);

    // Four holdings were given new figures in March; "Other assets" was not, so it alone
    // still carries its opening zero.
    $carried = collect($march->holdings)
        ->filter(fn (NetWorthHolding $holding): bool => $holding->isCarriedForward)
        ->pluck('kind');

    expect($march->netCents())->toBe(GoldenYear::MARCH_NET_WORTH)
        ->and($march->debtsCents)->toBe(GoldenYear::MARCH_DEBT)
        ->and($carried->all())->toBe([NetWorthItemKind::OtherAsset]);
});

it('measures the change since February and since the year began', function (): void {
    [, $year] = GoldenYear::build();

    $position = resolve(CalculateNetWorth::class)->handle($year);

    expect($position->latestRecordedMonth)->toBe(3)
        ->and($position->current()->netCents())->toBe(GoldenYear::MARCH_NET_WORTH)
        // February carried the opening figures, so both changes are the same number.
        ->and($position->changeVsPreviousMonth())->toBe(GoldenYear::NET_WORTH_CHANGE)
        ->and($position->changeVsStartOfYear())->toBe(GoldenYear::NET_WORTH_CHANGE);
});

it('breaks net worth down by kind', function (): void {
    [, $year] = GoldenYear::build();

    $byKind = resolve(CalculateNetWorth::class)->handle($year)->month(3)->byKind;

    expect($byKind[NetWorthItemKind::Cash->value])->toBe(GoldenYear::MARCH_CASH)
        ->and($byKind[NetWorthItemKind::EmergencyFund->value])->toBe(GoldenYear::MARCH_FUND)
        ->and($byKind[NetWorthItemKind::Investment->value])->toBe(GoldenYear::MARCH_INVESTMENT)
        ->and($byKind[NetWorthItemKind::Debt->value])->toBe(GoldenYear::MARCH_DEBT)
        ->and($byKind[NetWorthItemKind::OtherAsset->value])->toBe(0);
});

it('reads the emergency fund goal from what is set aside', function (): void {
    [$user, $year] = GoldenYear::build();

    $progress = resolve(CalculateGoalProgress::class)->handle($user, $year, m5Today());

    $fund = collect($progress)->firstWhere('type', GoalType::EmergencyFund);

    // Both sides come from elsewhere: neither is a figure the user typed on this screen.
    expect($fund?->currentCents)->toBe(GoldenYear::MARCH_FUND)
        ->and($fund?->targetCents)->toBe(GoldenYear::EMERGENCY_TARGET)
        ->and($fund?->isTracked)->toBeTrue();
});

it('reads the year end balance goal from the forecast', function (): void {
    [$user, $year] = GoldenYear::build();

    $progress = resolve(CalculateGoalProgress::class)->handle($user, $year, m5Today());

    $balance = goldenGoal($progress, 'Money left at the end of 2027');

    // The same year end the cash flow reports, reached a different way.
    expect($balance->currentCents)->toBe(GoldenYear::FORECAST_YEAR_END)
        ->and($balance->isTracked)->toBeTrue()
        ->and($balance->isReached)->toBeTrue()
        ->and($balance->estimatedCompletion?->toDateString())->toBe('2027-12-31');
});

it('reads an ordinary goal from what the user has told it', function (): void {
    [$user, $year] = GoldenYear::build();

    $kitchen = goldenGoal(
        resolve(CalculateGoalProgress::class)->handle($user, $year, m5Today()),
        'New kitchen',
    );

    // 3.000,00 short at 1.000,00 a month is three months from March.
    expect($kitchen->currentCents)->toBe(GoldenYear::PURCHASE_SAVED)
        ->and($kitchen->remainingCents)->toBe(GoldenYear::PURCHASE_TARGET - GoldenYear::PURCHASE_SAVED)
        ->and($kitchen->isTracked)->toBeFalse()
        ->and($kitchen->estimatedCompletion?->toDateString())->toBe('2027-06-01')
        ->and($kitchen->isOffTrack)->toBeFalse();
});

it('keeps the fund, the forecast and the goal telling the same story', function (): void {
    [$user, $year] = GoldenYear::build();

    $fundStatus = resolve(CalculateEmergencyFund::class)->handle($year, m5Today());
    $netWorth = resolve(CalculateNetWorth::class)->handle($year);
    $goals = resolve(CalculateGoalProgress::class)->handle($user, $year, m5Today());

    $fundGoal = collect($goals)->firstWhere('type', GoalType::EmergencyFund);

    // The fund appears in three places; if they ever disagree, one screen is lying.
    expect($fundStatus->currentCents)
        ->toBe($netWorth->month(3)->byKind[NetWorthItemKind::EmergencyFund->value])
        ->and($fundGoal?->currentCents)->toBe($fundStatus->currentCents)
        ->and($fundGoal?->targetCents)->toBe($fundStatus->targetCents);
});

it('counts the emergency fund as part of what the user has', function (): void {
    [, $year] = GoldenYear::build();

    $march = resolve(CalculateNetWorth::class)->handle($year)->month(3);

    // The fund is a holding like any other, not money held apart from net worth (§6.1).
    expect($march->assetsCents)
        ->toBe(GoldenYear::MARCH_CASH + GoldenYear::MARCH_FUND + GoldenYear::MARCH_INVESTMENT);
});
