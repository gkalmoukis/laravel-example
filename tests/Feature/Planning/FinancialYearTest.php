<?php

declare(strict_types=1);

use App\Actions\CapturePlanBaseline;
use App\Actions\CompleteYearSetup;
use App\Actions\CopyFinancialYear;
use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Actions\ProvisionUserDefaults;
use App\Actions\RemoveSalaryModel;
use App\Actions\SaveSalaryModel;
use App\Actions\UpdateOpeningPosition;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItem;
use App\Models\SalaryModel;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\QueryException;

function provisionedUserForYears(): User
{
    $user = User::factory()->create();

    resolve(ProvisionUserDefaults::class)->handle($user);

    return $user;
}

function holding(User $user, NetWorthItemKind $kind): NetWorthItem
{
    return $user->netWorthItems()->where('kind', $kind)->firstOrFail();
}

it('seeds the opening position with one holding per group', function (): void {
    $user = provisionedUserForYears();

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    expect($user->netWorthItems()->count())->toBe(5)
        ->and($year->netWorthSnapshots()->where('month', 0)->count())->toBe(5)
        ->and($year->netWorthSnapshots()->sum('value_cents'))->toBe(0);
});

it('reuses the same holdings for a second year', function (): void {
    $user = provisionedUserForYears();

    resolve(CreateFinancialYear::class)->handle($user, 2027);
    resolve(CreateFinancialYear::class)->handle($user, 2028);

    expect($user->netWorthItems()->count())->toBe(5);
});

it('records the opening position and reports the liquid balance', function (): void {
    $user = provisionedUserForYears();
    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    resolve(UpdateOpeningPosition::class)->handle($year, [
        holding($user, NetWorthItemKind::Cash)->id => Money::fromCents(500_000),
        holding($user, NetWorthItemKind::EmergencyFund)->id => Money::fromCents(300_000),
        holding($user, NetWorthItemKind::Investment)->id => Money::fromCents(1_000_000),
        holding($user, NetWorthItemKind::Debt)->id => Money::fromCents(200_000),
    ]);

    // Only cash and the emergency fund are spendable.
    expect(resolve(UpdateOpeningPosition::class)->openingLiquidBalance($year)->cents)->toBe(800_000)
        ->and(resolve(UpdateOpeningPosition::class)->openingNetWorth($year))->toBe(1_600_000);
});

it('reports a negative net worth when debts exceed what is owned', function (): void {
    $user = provisionedUserForYears();
    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    resolve(UpdateOpeningPosition::class)->handle($year, [
        holding($user, NetWorthItemKind::Cash)->id => Money::fromCents(100_000),
        holding($user, NetWorthItemKind::Debt)->id => Money::fromCents(450_000),
    ]);

    expect(resolve(UpdateOpeningPosition::class)->openingNetWorth($year))->toBe(-350_000);
});

it('ignores holdings belonging to someone else', function (): void {
    $user = provisionedUserForYears();
    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $someoneElses = NetWorthItem::factory()->create();

    resolve(UpdateOpeningPosition::class)->handle($year, [
        $someoneElses->id => Money::fromCents(999_000),
    ]);

    expect(NetWorthSnapshot::query()
        ->where('financial_year_id', $year->id)
        ->where('net_worth_item_id', $someoneElses->id)
        ->exists())->toBeFalse();
});

it('captures the plan as a baseline when setup finishes', function (): void {
    $user = provisionedUserForYears();
    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    resolve(UpdateOpeningPosition::class)->handle($year, [
        holding($user, NetWorthItemKind::Cash)->id => Money::fromCents(100_000),
    ]);

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();
    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();

    resolve(CreatePlanItem::class)->handle($year, [
        'type' => TransactionType::Income,
        'category_id' => $salary->id,
        'name' => 'Salary',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(200_000));

    resolve(CreatePlanItem::class)->handle($year, [
        'type' => TransactionType::Expense,
        'category_id' => $housing->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    $year = resolve(CompleteYearSetup::class)->handle($year);
    $baseline = $year->baseline;

    expect($year->isSetupComplete())->toBeTrue()
        ->and($year->hasBaseline())->toBeTrue()
        ->and($baseline['opening_balance_cents'])->toBe(100_000)
        ->and($baseline['annual_income_cents'])->toBe(2_400_000)
        ->and($baseline['annual_expenses_cents'])->toBe(840_000)
        ->and($baseline['annual_savings_cents'])->toBe(1_560_000)
        // 100.000 + 12 × (200.000 − 70.000)
        ->and($baseline['year_end_balance_cents'])->toBe(1_660_000)
        ->and($baseline['monthly_closing_balance_cents'][1])->toBe(230_000);
});

it('records a closing balance that goes below zero', function (): void {
    $user = provisionedUserForYears();
    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();

    resolve(CreatePlanItem::class)->handle($year, [
        'type' => TransactionType::Expense,
        'category_id' => $housing->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    $baseline = resolve(CapturePlanBaseline::class)->build($year);

    expect($baseline['year_end_balance_cents'])->toBe(-840_000)
        ->and($baseline['monthly_closing_balance_cents'][1])->toBe(-70_000);
});

it('copies the plan forward into a new year', function (): void {
    $user = provisionedUserForYears();
    $source = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();

    resolve(CreatePlanItem::class)->handle($source, [
        'type' => TransactionType::Expense,
        'category_id' => $housing->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    $target = resolve(CopyFinancialYear::class)->handle($user, $source, 2028);

    $copied = $target->planItems()->with('amounts')->firstOrFail();

    expect($target->year)->toBe(2028)
        ->and($target->copied_from_id)->toBe($source->id)
        ->and($copied->name)->toBe('Rent')
        ->and($copied->amounts)->toHaveCount(12)
        ->and($copied->amounts->sum(fn ($a): int => $a->amount_cents->cents))->toBe(840_000);
});

it('rebuilds the salary arrangement in the copied year rather than duplicating it', function (): void {
    $user = provisionedUserForYears();
    $source = resolve(CreateFinancialYear::class)->handle($user, 2027);

    resolve(SaveSalaryModel::class)->handle($source, Money::fromCents(180_000), SalaryModel::defaultPayments());

    $target = resolve(CopyFinancialYear::class)->handle($user, $source, 2028);

    expect($target->salaryModel()->firstOrFail()->base_amount_cents->cents)->toBe(180_000)
        ->and($target->planItems()->where('source', PlanItemSource::SalaryModel)->count())->toBe(4);
});

it('does not copy items generated from subscriptions', function (): void {
    $user = provisionedUserForYears();
    $source = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $category = $user->categories()->where('name', 'Subscriptions')->firstOrFail();

    PlanItem::factory()->for($source, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Subscription,
    ]);

    $target = resolve(CopyFinancialYear::class)->handle($user, $source, 2028);

    expect($target->planItems()->count())->toBe(0);
});

it('opens the new year where the old one closed', function (): void {
    $user = provisionedUserForYears();
    $source = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $cash = holding($user, NetWorthItemKind::Cash);

    NetWorthSnapshot::query()->create([
        'net_worth_item_id' => $cash->id,
        'financial_year_id' => $source->id,
        'month' => 12,
        'value_cents' => 750_000,
    ]);

    $target = resolve(CopyFinancialYear::class)->handle($user, $source, 2028);

    expect(resolve(UpdateOpeningPosition::class)->openingLiquidBalance($target)->cents)->toBe(750_000);
});

it('removes the salary arrangement and only what it generated', function (): void {
    $user = provisionedUserForYears();
    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();

    resolve(CreatePlanItem::class)->handle($year, [
        'type' => TransactionType::Expense,
        'category_id' => $housing->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    resolve(SaveSalaryModel::class)->handle($year, Money::fromCents(180_000), SalaryModel::defaultPayments());
    resolve(RemoveSalaryModel::class)->handle($year);

    expect($year->salaryModel()->exists())->toBeFalse()
        ->and($year->planItems()->where('source', PlanItemSource::SalaryModel)->count())->toBe(0)
        ->and($year->planItems()->where('source', PlanItemSource::Manual)->count())->toBe(1);
});

it('accepts only years inside the offered span', function (int $year, bool $expected): void {
    expect(CreateFinancialYear::isSelectableYear($year))->toBe($expected);
})->with([
    'too early' => [1999, false],
    'earliest' => [2000, true],
    'current' => [2026, true],
    'five years ahead' => [2031, true],
    'beyond the span' => [2032, false],
]);

it('keeps one year per user but allows the same year for different users', function (): void {
    $user = provisionedUserForYears();
    resolve(CreateFinancialYear::class)->handle($user, 2027);

    expect(fn (): FinancialYear => resolve(CreateFinancialYear::class)->handle($user, 2027))
        ->toThrow(QueryException::class);

    $other = provisionedUserForYears();

    expect(resolve(CreateFinancialYear::class)->handle($other, 2027)->year)->toBe(2027);
});
