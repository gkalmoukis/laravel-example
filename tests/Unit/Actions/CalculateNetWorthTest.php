<?php

declare(strict_types=1);

use App\Actions\CalculateNetWorth;
use App\Actions\CreateFinancialYear;
use App\Data\NetWorthPosition;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\User;

/*
 * §7.8 is normative. Every expectation is worked out by hand from the rules.
 */

function holdingOf(User $user, NetWorthItemKind $kind): NetWorthItem
{
    return $user->netWorthItems()->where('kind', $kind)->firstOrFail();
}

function valueAt(FinancialYear $year, NetWorthItem $item, int $month, int $cents): void
{
    NetWorthSnapshot::query()->updateOrCreate(
        [
            'net_worth_item_id' => $item->id,
            'financial_year_id' => $year->id,
            'month' => $month,
        ],
        ['value_cents' => $cents],
    );
}

function netWorth(FinancialYear $year): NetWorthPosition
{
    return resolve(CalculateNetWorth::class)->handle($year);
}

it('adds up everything owned and takes off everything owed', function (): void {
    [$user, $year] = userWithYear();

    valueAt($year, holdingOf($user, NetWorthItemKind::Cash), 1, 500_000);
    valueAt($year, holdingOf($user, NetWorthItemKind::EmergencyFund), 1, 300_000);
    valueAt($year, holdingOf($user, NetWorthItemKind::Investment), 1, 1_000_000);
    valueAt($year, holdingOf($user, NetWorthItemKind::OtherAsset), 1, 200_000);
    valueAt($year, holdingOf($user, NetWorthItemKind::Debt), 1, 400_000);

    $month = netWorth($year)->month(1);

    expect($month->assetsCents)->toBe(2_000_000)
        ->and($month->debtsCents)->toBe(400_000)
        ->and($month->netCents())->toBe(1_600_000);
});

it('lets net worth be negative', function (): void {
    [$user, $year] = userWithYear();

    valueAt($year, holdingOf($user, NetWorthItemKind::Cash), 1, 100_000);
    valueAt($year, holdingOf($user, NetWorthItemKind::Debt), 1, 900_000);

    // Owing more than you own is a real position, and the one thing this figure must
    // never hide.
    expect(netWorth($year)->month(1)->netCents())->toBe(-800_000);
});

it('holds a debt as what is still owed', function (): void {
    [$user, $year] = userWithYear();

    $debt = holdingOf($user, NetWorthItemKind::Debt);

    valueAt($year, $debt, 1, 250_000);

    $holding = collect(netWorth($year)->month(1)->holdings)
        ->firstWhere('itemId', $debt->id);

    // Stored and shown positive; the subtraction happens in the total.
    expect($holding?->valueCents)->toBe(250_000)
        ->and(netWorth($year)->month(1)->debtsCents)->toBe(250_000);
});

it('carries a value forward into a month that was skipped', function (): void {
    [$user, $year] = userWithYear();

    $investment = holdingOf($user, NetWorthItemKind::Investment);

    valueAt($year, $investment, 3, 1_000_000);

    $position = netWorth($year);

    $march = collect($position->month(3)->holdings)->firstWhere('itemId', $investment->id);
    $april = collect($position->month(4)->holdings)->firstWhere('itemId', $investment->id);

    // A house does not vanish in April because nobody valued it again.
    expect($march?->valueCents)->toBe(1_000_000)
        ->and($march?->isCarriedForward)->toBeFalse()
        ->and($april?->valueCents)->toBe(1_000_000)
        ->and($april?->isCarriedForward)->toBeTrue();
});

it('carries the opening position forward like any other value', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($year, $cash, NetWorthSnapshot::OPENING_MONTH, 250_000);

    $position = netWorth($year);

    expect($position->month(0)->netCents())->toBe(250_000)
        ->and($position->month(6)->netCents())->toBe(250_000)
        ->and(collect($position->month(6)->holdings)->firstWhere('itemId', $cash->id)?->isCarriedForward)
        ->toBeTrue();
});

it('replaces a carried value once a newer one is given', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($year, $cash, 1, 100_000);
    valueAt($year, $cash, 5, 300_000);

    $position = netWorth($year);

    expect($position->month(4)->netCents())->toBe(100_000)
        ->and($position->month(5)->netCents())->toBe(300_000)
        ->and(collect($position->month(5)->holdings)->firstWhere('itemId', $cash->id)?->isCarriedForward)
        ->toBeFalse();
});

it('shows nothing rather than stale for a holding never valued', function (): void {
    [$user, $year] = userWithYear();

    // Creating a year opens every holding at zero, so "never valued" means a holding
    // added afterwards, which has no opening row behind it.
    $boat = NetWorthItem::factory()->for($user)->create([
        'kind' => NetWorthItemKind::OtherAsset,
        'name' => 'Boat',
        'is_active' => true,
    ]);

    $holding = collect(netWorth($year)->month(6)->holdings)
        ->firstWhere('itemId', $boat->id);

    expect($holding?->valueCents)->toBe(0)
        ->and($holding?->isCarriedForward)->toBeFalse();
});

it('opens every holding the year was created with at zero', function (): void {
    [$user, $year] = userWithYear();

    $investment = holdingOf($user, NetWorthItemKind::Investment);

    // Zero is a value the user was given, not an absence — so later months carry it.
    $opening = collect(netWorth($year)->month(0)->holdings)
        ->firstWhere('itemId', $investment->id);

    $later = collect(netWorth($year)->month(6)->holdings)
        ->firstWhere('itemId', $investment->id);

    expect($opening?->isCarriedForward)->toBeFalse()
        ->and($later?->valueCents)->toBe(0)
        ->and($later?->isCarriedForward)->toBeTrue();
});

it('leaves retired holdings out entirely', function (): void {
    [$user, $year] = userWithYear();

    $investment = holdingOf($user, NetWorthItemKind::Investment);

    valueAt($year, $investment, 1, 1_000_000);
    $investment->update(['is_active' => false]);

    $position = netWorth($year);

    // Carrying a sold investment forever would keep counting something gone (NW-02).
    expect($position->month(1)->assetsCents)->toBe(0)
        ->and(collect($position->month(1)->holdings)->pluck('itemId'))->not->toContain($investment->id);
});

it('breaks the total down by kind', function (): void {
    [$user, $year] = userWithYear();

    valueAt($year, holdingOf($user, NetWorthItemKind::Cash), 1, 500_000);
    valueAt($year, holdingOf($user, NetWorthItemKind::Debt), 1, 200_000);

    $byKind = netWorth($year)->month(1)->byKind;

    expect($byKind[NetWorthItemKind::Cash->value])->toBe(500_000)
        ->and($byKind[NetWorthItemKind::Debt->value])->toBe(200_000)
        // Every kind is reported, so a chart has no gaps to guess at.
        ->and($byKind[NetWorthItemKind::Investment->value])->toBe(0);
});

it('reports the opening position and all twelve months', function (): void {
    [, $year] = userWithYear();

    $position = netWorth($year);

    expect($position->months)->toHaveCount(13)
        ->and($position->year)->toBe(2027)
        ->and($position->month(NetWorthSnapshot::OPENING_MONTH)->month)->toBe(0)
        ->and($position->month(12)->month)->toBe(12);
});

it('knows the last month the user told it anything', function (): void {
    [$user, $year] = userWithYear();

    valueAt($year, holdingOf($user, NetWorthItemKind::Cash), 4, 100_000);

    expect(netWorth($year)->latestRecordedMonth)->toBe(4)
        ->and(netWorth($year)->current()->month)->toBe(4);
});

it('falls back to the opening position when nothing has been recorded', function (): void {
    [, $year] = userWithYear();

    $position = netWorth($year);

    // Provisioning creates month 0 rows at zero, which is a real starting point.
    expect($position->latestRecordedMonth)->toBe(0)
        ->and($position->current()->month)->toBe(0)
        ->and($position->current()->netCents())->toBe(0);
});

it('says how much changed since last month', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($year, $cash, 3, 100_000);
    valueAt($year, $cash, 4, 175_000);

    expect(netWorth($year)->changeVsPreviousMonth())->toBe(75_000);
});

it('compares against the month before even when it was skipped', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($year, $cash, 2, 100_000);
    valueAt($year, $cash, 6, 150_000);

    // May carries February's figure forward, so the change is measured against that.
    expect(netWorth($year)->changeVsPreviousMonth())->toBe(50_000);
});

it('has nothing to compare in the opening position', function (): void {
    [, $year] = userWithYear();

    // There is no month before the start of the year.
    expect(netWorth($year)->changeVsPreviousMonth())->toBeNull();
});

it('says how much changed since the year began', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($year, $cash, NetWorthSnapshot::OPENING_MONTH, 100_000);
    valueAt($year, $cash, 8, 450_000);

    expect(netWorth($year)->changeVsStartOfYear())->toBe(350_000);
});

it('reports a fall as a negative change', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($year, $cash, NetWorthSnapshot::OPENING_MONTH, 500_000);
    valueAt($year, $cash, 4, 200_000);

    expect(netWorth($year)->changeVsStartOfYear())->toBe(-300_000)
        ->and(netWorth($year)->changeVsPreviousMonth())->toBe(-300_000);
});

it('keeps each year to itself', function (): void {
    $user = planningUser();

    $twentySeven = resolve(CreateFinancialYear::class)->handle($user, 2027);
    $twentyEight = resolve(CreateFinancialYear::class)->handle($user, 2028);

    $cash = holdingOf($user, NetWorthItemKind::Cash);

    valueAt($twentySeven, $cash, 6, 900_000);

    expect(netWorth($twentySeven)->month(6)->netCents())->toBe(900_000)
        ->and(netWorth($twentyEight)->month(6)->netCents())->toBe(0);
});

it('never counts another user holdings', function (): void {
    [$user, $year] = userWithYear();
    [$other, $otherYear] = userWithYear(2026);

    valueAt($year, holdingOf($user, NetWorthItemKind::Cash), 1, 100_000);
    valueAt($otherYear, holdingOf($other, NetWorthItemKind::Cash), 1, 999_999);

    expect(netWorth($year)->month(1)->netCents())->toBe(100_000);
});
