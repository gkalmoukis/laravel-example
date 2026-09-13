<?php

declare(strict_types=1);
use App\Actions\SaveSalaryModel;
use App\Enums\PlanItemSource;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\Models\PlanItemAmount;
use App\Models\SalaryModel;
use App\ValueObjects\Money;

/**
 * The monthly amounts as plain cents, since the column casts to a Money object.
 *
 * @return array<int, int>
 */
function monthlyCents(PlanItem $item): array
{
    return $item->amounts()->orderBy('month')->get()
        ->mapWithKeys(fn (PlanItemAmount $amount): array => [$amount->month => $amount->amount_cents->cents])
        ->all();
}

function saveSalary(FinancialYear $year, int $cents = 180_000, ?array $payments = null): SalaryModel
{
    return resolve(SaveSalaryModel::class)->handle(
        $year,
        Money::fromCents($cents),
        $payments ?? SalaryModel::defaultPayments(),
    );
}

it('generates a salary and three bonuses on the fourteen-payment model', function (): void {
    $year = planningYear();

    saveSalary($year);

    $items = $year->planItems()->where('source', PlanItemSource::SalaryModel)->pluck('name');

    expect($items)->toHaveCount(4)
        ->and($items->all())->toBe(['Salary', 'Christmas Bonus', 'Easter Bonus', 'Vacation Allowance']);
});

it('pays the salary every month and each bonus once', function (): void {
    $year = planningYear();

    saveSalary($year, 180_000);

    $salary = $year->planItems()->where('name', 'Salary')->firstOrFail();
    $christmas = $year->planItems()->where('name', 'Christmas Bonus')->firstOrFail();

    $salaryMonths = monthlyCents($salary);
    $christmasMonths = monthlyCents($christmas);

    expect(count(array_filter($salaryMonths)))->toBe(12)
        ->and(array_sum($salaryMonths))->toBe(2_160_000)
        ->and(count(array_filter($christmasMonths)))->toBe(1)
        ->and($christmasMonths[12])->toBe(180_000);
});

it('places each bonus in its customary month', function (string $name, int $month): void {
    $year = planningYear();

    saveSalary($year);

    $amounts = monthlyCents($year->planItems()->where('name', $name)->firstOrFail());

    expect($amounts[$month])->toBeGreaterThan(0)
        ->and(count(array_filter($amounts)))->toBe(1);
})->with([
    'Christmas in December' => ['Christmas Bonus', 12],
    'Easter in April' => ['Easter Bonus', 4],
    'Vacation before summer' => ['Vacation Allowance', 6],
]);

it('halves the salary for the half-salary bonuses', function (): void {
    $year = planningYear();

    saveSalary($year, 180_000);

    $easter = $year->planItems()->where('name', 'Easter Bonus')->firstOrFail();

    expect($easter->amounts()->where('month', 4)->firstOrFail()->amount_cents->cents)->toBe(90_000);
});

it('rounds a half-salary bonus half up, to the cent', function (): void {
    $year = planningYear();

    // An odd number of cents: half of 1.234,57 € is 617,285, which rounds up.
    saveSalary($year, 123_457);

    $easter = $year->planItems()->where('name', 'Easter Bonus')->firstOrFail();

    expect($easter->amounts()->where('month', 4)->firstOrFail()->amount_cents->cents)->toBe(61_729);
});

it('generates only the salary on the twelve-payment model', function (): void {
    $year = planningYear();

    saveSalary($year, 180_000, SalaryModel::defaultPayments(enabled: false));

    expect($year->planItems()->where('source', PlanItemSource::SalaryModel)->pluck('name')->all())
        ->toBe(['Salary']);
});

it('uses a fixed amount when the bonus is not a multiple of salary', function (): void {
    $year = planningYear();

    $payments = SalaryModel::defaultPayments();
    $payments[SalaryModel::CHRISTMAS_BONUS]['mode'] = SalaryModel::MODE_FIXED_AMOUNT;
    $payments[SalaryModel::CHRISTMAS_BONUS]['amount_cents'] = 55_000;

    saveSalary($year, 180_000, $payments);

    $christmas = $year->planItems()->where('name', 'Christmas Bonus')->firstOrFail();

    expect($christmas->amounts()->where('month', 12)->firstOrFail()->amount_cents->cents)->toBe(55_000);
});

it('honours a bonus moved to another month', function (): void {
    $year = planningYear();

    $payments = SalaryModel::defaultPayments();
    $payments[SalaryModel::EASTER_BONUS]['month'] = 5;

    saveSalary($year, 180_000, $payments);

    $amounts = monthlyCents($year->planItems()->where('name', 'Easter Bonus')->firstOrFail());

    expect($amounts[5])->toBe(90_000)->and($amounts[4])->toBe(0);
});

it('replaces what it generated before rather than adding to it', function (): void {
    $year = planningYear();

    saveSalary($year, 180_000);
    saveSalary($year, 200_000);

    $items = $year->planItems()->where('source', PlanItemSource::SalaryModel)->get();

    expect($items)->toHaveCount(4)
        ->and($year->salaryModel()->firstOrFail()->base_amount_cents->cents)->toBe(200_000)
        ->and($items->firstWhere('name', 'Salary')?->amounts()->where('month', 1)->firstOrFail()->amount_cents->cents)
        ->toBe(200_000);
});

it('never disturbs income the user planned by hand', function (): void {
    $year = planningYear();
    $category = Category::query()->where('user_id', $year->user_id)->firstOrFail();

    $manual = PlanItem::factory()->for($year, 'financialYear')->income()->create([
        'category_id' => $category->id,
        'name' => 'Freelance work',
    ]);

    saveSalary($year);
    saveSalary($year, 200_000);

    expect(PlanItem::query()->whereKey($manual->id)->exists())->toBeTrue()
        ->and($year->planItems()->where('source', PlanItemSource::Manual)->count())->toBe(1);
});

it('files each payment under its own category', function (): void {
    $year = planningYear();

    saveSalary($year);

    $keys = $year->planItems()
        ->with('category')
        ->where('source', PlanItemSource::SalaryModel)
        ->get()
        ->map(fn (PlanItem $item): ?string => $item->category->system_key)
        ->all();

    expect($keys)->toBe([
        Category::KEY_SALARY,
        Category::KEY_CHRISTMAS_BONUS,
        Category::KEY_EASTER_BONUS,
        Category::KEY_VACATION_ALLOWANCE,
    ]);
});

it('writes twelve amounts for every generated item', function (): void {
    $year = planningYear();

    saveSalary($year);

    foreach ($year->planItems()->with('amounts')->get() as $item) {
        expect($item->amounts)->toHaveCount(12);
    }
});
