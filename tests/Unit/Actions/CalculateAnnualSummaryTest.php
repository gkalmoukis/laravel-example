<?php

declare(strict_types=1);

use App\Actions\CalculateAnnualSummary;
use App\Actions\CapturePlanBaseline;
use App\Actions\CreatePlanItem;
use App\Data\AnnualSummary;
use App\Enums\Frequency;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/*
 * §7.3 is normative. Every expectation is worked out by hand from the rules.
 */

function planFor(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function summaryRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function summary(FinancialYear $year, string $today = '2027-06-15'): AnnualSummary
{
    return resolve(CalculateAnnualSummary::class)->handle($year, CarbonImmutable::parse($today));
}

it('adds the plan up over twelve months', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Salary', 200_000, 'income');
    planFor($year, $user, 'Housing', 70_000);

    $result = summary($year);

    expect($result->plannedIncomeCents)->toBe(2_400_000)
        ->and($result->plannedExpenseCents)->toBe(840_000)
        ->and($result->plannedSavingsCents())->toBe(1_560_000);
});

it('counts everything recorded up to today as actual so far', function (): void {
    [$user, $year] = userWithYear();

    summaryRecord($user, 'Salary', '2027-01-25', 200_000);
    summaryRecord($user, 'Housing', '2027-02-05', 70_000);
    // Dated after today: not yet part of "so far".
    summaryRecord($user, 'Housing', '2027-08-05', 99_999);

    $result = summary($year, '2027-06-15');

    expect($result->actualSoFarIncomeCents)->toBe(200_000)
        ->and($result->actualSoFarExpenseCents)->toBe(70_000)
        ->and($result->actualSoFarSavingsCents())->toBe(130_000);
});

it('counts actual so far whatever state the month is in', function (): void {
    [$user, $year] = userWithYear();

    // Nothing here is signed off, but the dashboard still shows it (§7.3).
    summaryRecord($user, 'Housing', '2027-03-05', 12_345);

    expect(summary($year)->actualSoFarExpenseCents)->toBe(12_345);
});

it('compares only finished months against the plan for those months', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);

    summaryRecord($user, 'Housing', '2027-01-05', 60_000);
    summaryRecord($user, 'Housing', '2027-02-05', 80_000);
    summaryRecord($user, 'Housing', '2027-03-05', 10_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);

    $result = summary($year);

    // Two finished months: 600,00 + 800,00 actual against 700,00 × 2 planned. March is
    // left out of both sides, or a part month would be measured against a whole one.
    expect($result->completedMonths)->toBe(2)
        ->and($result->completedExpenseCents)->toBe(140_000)
        ->and($result->completedPlannedExpenseCents)->toBe(140_000);
});

it('has nothing to compare before any month is finished', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);
    summaryRecord($user, 'Housing', '2027-01-05', 60_000);

    $result = summary($year);

    expect($result->completedMonths)->toBe(0)
        ->and($result->completedExpenseCents)->toBe(0)
        ->and($result->completedPlannedExpenseCents)->toBe(0);
});

it('forecasts the year from finished months and the plan for the rest', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);

    summaryRecord($user, 'Housing', '2027-01-05', 50_000);
    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    // January is done at 500,00; the other eleven months keep their 700,00 plan.
    expect(summary($year)->forecastExpenseCents)->toBe(50_000 + 11 * 70_000);
});

it('measures drift against the plan the year started with', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);

    resolve(CapturePlanBaseline::class)->handle($year->refresh());

    // The plan is then cut back, which would flatter the year if drift were measured
    // against the plan as edited rather than as captured (FC-06).
    $year->planItems()->delete();

    $result = summary($year->refresh());

    expect($result->hasBaseline)->toBeTrue()
        // Forecast year end is now 0; the baseline expected −840.000 cents.
        ->and($result->forecastYearEndCents)->toBe(0)
        ->and($result->deviationCents)->toBe(840_000);
});

it('falls back to the current plan when no baseline was captured', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);

    $result = summary($year);

    // Nothing to drift from, so forecast and plan agree and the caller is told there is
    // no baseline behind the number.
    expect($result->hasBaseline)->toBeFalse()
        ->and($result->deviationCents)->toBe(0)
        ->and($result->plannedYearEndCents)->toBe($result->forecastYearEndCents);
});

it('shows drift from the current plan when reality has overtaken it', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);

    // Spent 200,00 more than planned in January, and January is not finished, so the
    // forecast takes the larger figure and the year ends 200,00 worse off.
    summaryRecord($user, 'Housing', '2027-01-05', 90_000);

    expect(summary($year)->deviationCents)->toBe(-20_000);
});

it('reports a year end balance for both plan and forecast', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Salary', 100_000, 'income');

    $result = summary($year);

    expect($result->plannedYearEndCents)->toBe(1_200_000)
        ->and($result->forecastYearEndCents)->toBe(1_200_000)
        ->and($result->forecastSavingsCents())->toBe(1_200_000);
});

it('reports nothing earned rather than a rate when there is no income', function (): void {
    [$user, $year] = userWithYear();

    planFor($year, $user, 'Housing', 70_000);

    // Savings rate is income ÷ savings, so the interface needs to see a zero income to
    // know to show "—" rather than divide by it (EDGE-03).
    $result = summary($year);

    expect($result->plannedIncomeCents)->toBe(0)
        ->and($result->plannedSavingsCents())->toBe(-840_000);
});
