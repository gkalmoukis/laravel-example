<?php

declare(strict_types=1);

use App\Actions\CalculateVariances;
use App\Actions\CreatePlanItem;
use App\Data\Variance;
use App\Data\VarianceReport;
use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Enums\VarianceStatus;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

/*
 * §7.4 is normative, including the boundaries: the threshold decides whether a category is
 * merely over or properly over, so each edge is pinned exactly rather than approximately.
 */

function variancePlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function varianceRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

function variances(FinancialYear $year, ?int $month = 1): VarianceReport
{
    return resolve(CalculateVariances::class)->handle($year, $month);
}

function varianceOf(VarianceReport $report, string $categoryName): Variance
{
    foreach ([...$report->expenses, ...$report->income] as $variance) {
        if ($variance->categoryName === $categoryName) {
            return $variance;
        }
    }

    throw new RuntimeException('No variance for '.$categoryName);
}

it('reports spending under plan as within budget', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    varianceRecord($user, 'Housing', '2027-01-05', 60_000);

    $housing = varianceOf(variances($year), 'Housing');

    expect($housing->plannedCents)->toBe(70_000)
        ->and($housing->actualCents)->toBe(60_000)
        ->and($housing->varianceCents)->toBe(-10_000)
        ->and($housing->status)->toBe(VarianceStatus::Ok)
        ->and($housing->label())->toBe('Within budget');
});

it('treats spending exactly on plan as within budget', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    varianceRecord($user, 'Housing', '2027-01-05', 70_000);

    expect(varianceOf(variances($year), 'Housing')->status)->toBe(VarianceStatus::Ok);
});

it('calls spending a penny over plan a warning', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    varianceRecord($user, 'Housing', '2027-01-05', 70_001);

    expect(varianceOf(variances($year), 'Housing')->status)->toBe(VarianceStatus::Warning);
});

it('holds a warning right up to the threshold', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    // Default threshold is 10%, so 770,00 € is the last amount that is only a warning.
    varianceRecord($user, 'Housing', '2027-01-05', 77_000);

    expect(varianceOf(variances($year), 'Housing')->status)->toBe(VarianceStatus::Warning);
});

it('calls one penny past the threshold over budget', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    varianceRecord($user, 'Housing', '2027-01-05', 77_001);

    $housing = varianceOf(variances($year), 'Housing');

    expect($housing->status)->toBe(VarianceStatus::Over)
        ->and($housing->label())->toBe('Over budget');
});

it('respects a threshold the user has changed', function (): void {
    [$user, $year] = userWithYear();

    $user->preference->update(['budget_warning_threshold_percent' => 50]);

    variancePlan($year, $user, 'Housing', 70_000);
    varianceRecord($user, 'Housing', '2027-01-05', 100_000);

    // Half over plan is still only a warning when the user allows 50%.
    expect(varianceOf(variances($year->refresh()), 'Housing')->status)->toBe(VarianceStatus::Warning);
});

it('calls unplanned spending over budget outright', function (): void {
    [$user, $year] = userWithYear();

    varianceRecord($user, 'Miscellaneous', '2027-01-05', 5_000);

    // No plan at all and money went out: there is no threshold to be within (§7.4).
    expect(varianceOf(variances($year), 'Miscellaneous')->status)->toBe(VarianceStatus::Over);
});

it('says nothing about a category with no plan and no spending', function (): void {
    [, $year] = userWithYear();

    $housing = varianceOf(variances($year), 'Housing');

    expect($housing->status)->toBe(VarianceStatus::NoPlan)
        ->and($housing->label())->toBe('No plan')
        ->and($housing->varianceCents)->toBe(0);
});

it('reports income at or above target as on target', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Salary', 200_000, 'income');
    varianceRecord($user, 'Salary', '2027-01-25', 200_000);

    $salary = varianceOf(variances($year), 'Salary');

    expect($salary->status)->toBe(VarianceStatus::Ok)
        ->and($salary->label())->toBe('On target');
});

it('calls income a little short slightly under', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Salary', 200_000, 'income');
    // 10% under is the edge: 1.800,00 € is still only slightly under.
    varianceRecord($user, 'Salary', '2027-01-25', 180_000);

    $salary = varianceOf(variances($year), 'Salary');

    expect($salary->status)->toBe(VarianceStatus::Warning)
        ->and($salary->label())->toBe('Slightly under');
});

it('calls income well short under target, never over budget', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Salary', 200_000, 'income');
    varianceRecord($user, 'Salary', '2027-01-25', 179_999);

    $salary = varianceOf(variances($year), 'Salary');

    expect($salary->status)->toBe(VarianceStatus::Over)
        // Income that fell short has not gone over budget; saying so would be nonsense.
        ->and($salary->label())->toBe('Under target');
});

it('treats unplanned income as on target', function (): void {
    [$user, $year] = userWithYear();

    varianceRecord($user, 'Overtime', '2027-01-25', 50_000);

    expect(varianceOf(variances($year), 'Overtime')->status)->toBe(VarianceStatus::Ok);
});

it('judges money set aside monthly on the year so far', function (): void {
    [$user, $year] = userWithYear();

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Holidays')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Summer holiday',
        'kind' => PlanItemKind::Irregular,
        'frequency' => Frequency::Annual,
        'start_month' => 8,
        'allocation' => Allocation::Spread,
    ], Money::fromCents(120_000));

    // 1.200,00 € spread over twelve months is 100,00 € a month. By March the plan has set
    // aside 300,00 € and nothing has been spent — which per month would look like three
    // separate underspends rather than one running total (CMP-05).
    $holidays = varianceOf(variances($year, 3), 'Holidays');

    expect($holidays->isSpread)->toBeTrue()
        ->and($holidays->plannedCents)->toBe(30_000)
        ->and($holidays->actualCents)->toBe(0);
});

it('judges an ordinary category on the single month asked for', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    varianceRecord($user, 'Housing', '2027-01-05', 70_000);
    varianceRecord($user, 'Housing', '2027-02-05', 70_000);

    $housing = varianceOf(variances($year, 3), 'Housing');

    expect($housing->isSpread)->toBeFalse()
        ->and($housing->plannedCents)->toBe(70_000)
        ->and($housing->actualCents)->toBe(0);
});

it('does not treat a category with a lump sum item as spread', function (): void {
    [$user, $year] = userWithYear();

    $holidays = $user->categories()->where('name', 'Holidays')->whereNull('parent_id')->firstOrFail();

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $holidays->id,
        'name' => 'Set aside',
        'kind' => PlanItemKind::Irregular,
        'frequency' => Frequency::Annual,
        'start_month' => 8,
        'allocation' => Allocation::Spread,
    ], Money::fromCents(120_000));

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $holidays->id,
        'name' => 'Paid in August',
        'kind' => PlanItemKind::Irregular,
        'frequency' => Frequency::Annual,
        'start_month' => 8,
        'allocation' => Allocation::LumpSum,
    ], Money::fromCents(50_000));

    // One of the two lands in a single month, so the category is read month by month.
    expect(varianceOf(variances($year, 3), 'Holidays')->isSpread)->toBeFalse();
});

it('sums only finished months in the year to date view', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);

    varianceRecord($user, 'Housing', '2027-01-05', 60_000);
    varianceRecord($user, 'Housing', '2027-02-05', 80_000);
    varianceRecord($user, 'Housing', '2027-03-05', 99_999);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);

    $report = variances($year, null);
    $housing = varianceOf($report, 'Housing');

    expect($report->isYearToDate())->toBeTrue()
        ->and($report->completedMonths)->toBe(2)
        ->and($housing->plannedCents)->toBe(140_000)
        ->and($housing->actualCents)->toBe(140_000)
        ->and($housing->status)->toBe(VarianceStatus::Ok);
});

it('puts the categories that went wrong first', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 70_000);
    variancePlan($year, $user, 'Utilities', 20_000);
    variancePlan($year, $user, 'Transportation', 10_000);

    // Housing is a little over, Utilities is far over, Transportation is fine.
    varianceRecord($user, 'Housing', '2027-01-05', 73_000);
    varianceRecord($user, 'Utilities', '2027-01-05', 60_000);
    varianceRecord($user, 'Transportation', '2027-01-05', 5_000);

    $names = collect(variances($year)->expenses)->pluck('categoryName')->take(2)->all();

    expect($names)->toBe(['Utilities', 'Housing']);
});

it('ranks the biggest problem first among equally bad ones', function (): void {
    [$user, $year] = userWithYear();

    variancePlan($year, $user, 'Housing', 10_000);
    variancePlan($year, $user, 'Utilities', 10_000);

    varianceRecord($user, 'Housing', '2027-01-05', 20_000);
    varianceRecord($user, 'Utilities', '2027-01-05', 90_000);

    expect(collect(variances($year)->expenses)->first()?->categoryName)->toBe('Utilities');
});

it('keeps income and expenses in separate lists', function (): void {
    [$user, $year] = userWithYear();

    $report = variances($year);

    expect(collect($report->income)->pluck('type')->unique()->all())->toBe([TransactionType::Income])
        ->and(collect($report->expenses)->pluck('type')->unique()->all())->toBe([TransactionType::Expense]);
});
