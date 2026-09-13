<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\ProvisionUserDefaults;
use App\Actions\RemoveSalaryModel;
use App\Actions\SaveSalaryModel;
use App\Actions\SyncPlanItemAmounts;
use App\Actions\UpdateBudgetCell;
use App\Actions\UpdateOpeningPosition;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItem;
use App\Models\SalaryModel;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Gate;

it('does nothing when there is no salary arrangement to remove', function (): void {
    [, $year] = userWithYear();

    resolve(RemoveSalaryModel::class)->handle($year);

    expect($year->salaryModel()->exists())->toBeFalse();
});

it('treats a bonus with a nonsense multiplier as nothing', function (): void {
    [, $year] = userWithYear();

    $payments = SalaryModel::defaultPayments();
    $payments[SalaryModel::CHRISTMAS_BONUS]['multiplier'] = 'half';

    resolve(SaveSalaryModel::class)->handle(
        $year,
        Money::fromCents(180_000),
        $payments,
    );

    $christmas = $year->planItems()->where('name', 'Christmas Bonus')->firstOrFail();

    expect((int) $christmas->amounts()->sum('amount_cents'))->toBe(0);
});

it('falls back to December when a bonus month is out of range', function (): void {
    [, $year] = userWithYear();

    $payments = SalaryModel::defaultPayments();
    $payments[SalaryModel::EASTER_BONUS]['month'] = 99;

    resolve(SaveSalaryModel::class)->handle(
        $year,
        Money::fromCents(180_000),
        $payments,
    );

    $easter = $year->planItems()->where('name', 'Easter Bonus')->firstOrFail();

    expect($easter->amounts()->where('month', 12)->firstOrFail()->amount_cents->cents)->toBe(90_000);
});

it('skips a payment whose settings are not a set of values', function (): void {
    [, $year] = userWithYear();

    $payments = SalaryModel::defaultPayments();
    $payments[SalaryModel::CHRISTMAS_BONUS] = 'nonsense';

    resolve(SaveSalaryModel::class)->handle(
        $year,
        Money::fromCents(180_000),
        $payments,
    );

    expect($year->planItems()->where('name', 'Christmas Bonus')->exists())->toBeFalse();
});

it('leaves an inactive holding out of the opening net worth', function (): void {
    [$user, $year] = userWithYear();

    $retired = NetWorthItem::factory()->for($user)->inactive()
        ->ofKind(NetWorthItemKind::Investment)->create();

    NetWorthSnapshot::query()->create([
        'net_worth_item_id' => $retired->id,
        'financial_year_id' => $year->id,
        'month' => NetWorthSnapshot::OPENING_MONTH,
        'value_cents' => 900_000,
    ]);

    expect(resolve(UpdateOpeningPosition::class)->openingNetWorth($year))->toBe(0);
});

it('suggests the next free year when the current one is taken', function (): void {
    $user = User::factory()->create();
    resolve(ProvisionUserDefaults::class)->handle($user);

    resolve(CreateFinancialYear::class)->handle($user, (int) date('Y'));

    $this->actingAs($user)
        ->get(route('financial-years.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('years/create')
            ->where('suggestedYear', (int) date('Y') + 1)
            ->has('copyableYears', 1));
});

it('edits a plan item without touching its months', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->firstOrFail();

    $this->actingAs($user)->post(route('plan-items.store', ['year' => 2027]), [
        'name' => 'Rent',
        'type' => 'expense',
        'kind' => 'recurring',
        'frequency' => 'monthly',
        'start_month' => 1,
        'category_id' => $category->id,
        'amount' => '700,00',
    ]);

    $item = $year->planItems()->where('name', 'Rent')->firstOrFail();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'name' => 'Rent and service charge',
        ])
        ->assertSessionHasNoErrors();

    expect($item->refresh()->name)->toBe('Rent and service charge')
        ->and((int) $item->amounts()->sum('amount_cents'))->toBe(840_000);
});

it('deletes a plan item the user created', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $user->categories()->firstOrFail()->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->delete(route('plan-items.destroy', ['year' => 2027, 'planItem' => $item->id]))
        ->assertSessionHasNoErrors();

    expect(PlanItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

it('rejects an amount that is not a number', function (string $route, array $payload): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route($route, ['year' => 2027]), $payload)
        ->assertSessionHasErrors('amount');
})->with([
    'budget cell' => ['budget-cell.update', ['month' => 1, 'amount' => 'lots']],
]);

it('rejects a salary that is not a number', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'income']))
        ->patch(route('salary-model.update', ['year' => 2027]), [
            'base_amount' => 'plenty',
            'payments' => SalaryModel::defaultPayments(),
        ])
        ->assertSessionHasErrors('base_amount');
});

it('rejects a plan item amount that is not a number', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Rent',
            'type' => 'expense',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'category_id' => $user->categories()->firstOrFail()->id,
            'amount' => 'a lot',
        ])
        ->assertSessionHasErrors('amount');
});

it('accepts custom months for an item paid in chosen months', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)->post(route('plan-items.store', ['year' => 2027]), [
        'name' => 'Car service',
        'type' => 'expense',
        'kind' => 'irregular',
        'frequency' => 'custom',
        'start_month' => 1,
        'category_id' => $user->categories()->where('name', 'Car Expenses')->firstOrFail()->id,
        'amount' => '150,00',
        'custom_months' => [3, 9],
    ]);

    $item = $year->planItems()->where('name', 'Car service')->firstOrFail();
    $months = $item->amounts()->pluck('amount_cents', 'month');

    expect((int) $months[3]->cents)->toBe(15_000)
        ->and((int) $months[9]->cents)->toBe(15_000)
        ->and((int) $item->amounts()->sum('amount_cents'))->toBe(30_000);
});

it('shows the budget grid with a row for every expense category', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plan/expenses')
            ->has('rows', 15)
            ->where('footerAnnualCents', 0));
});

it('marks a category with several items as not editable in the grid', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->firstOrFail();

    PlanItem::factory()->count(2)->for($year, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        // Housing is the first expense category and now holds two planned items, so the
        // grid cannot tell which one a typed number belongs to.
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.categoryName', 'Housing')
            ->where('rows.0.isEditable', false)
            ->where('rows.0.itemCount', 2));
});

it('knows when a plan item comes from a subscription rather than the user', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->spread()->create([
        'category_id' => $user->categories()->firstOrFail()->id,
        'source' => PlanItemSource::Subscription,
    ]);

    expect($item->isManual())->toBeFalse()
        ->and($item->isSpread())->toBeTrue();
});

it('knows which holdings hold spendable money', function (NetWorthItemKind $kind, bool $liquid): void {
    expect(NetWorthItem::factory()->ofKind($kind)->make()->isLiquid())->toBe($liquid);
})->with([
    'cash' => [NetWorthItemKind::Cash, true],
    'emergency fund' => [NetWorthItemKind::EmergencyFund, true],
    'investment' => [NetWorthItemKind::Investment, false],
    'debt' => [NetWorthItemKind::Debt, false],
]);

it('links a plan item and its amounts back to their owners', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $user->categories()->firstOrFail()->id,
        'subcategory_id' => null,
        'salary_model_id' => null,
    ]);

    $amount = $item->amounts()->create(['month' => 1, 'amount_cents' => 1000]);
    $snapshot = $year->netWorthSnapshots()->where('month', 0)->firstOrFail();

    expect($item->financialYear->id)->toBe($year->id)
        ->and($item->category->id)->toBe($user->categories()->firstOrFail()->id)
        ->and($item->subcategory)->toBeNull()
        ->and($item->salaryModel)->toBeNull()
        ->and($amount->planItem->id)->toBe($item->id)
        ->and($snapshot->netWorthItem->user_id)->toBe($user->id)
        ->and($snapshot->financialYear->id)->toBe($year->id);
});

it('links a salary arrangement and a year-end goal back to their year', function (): void {
    [$user, $year] = userWithYear();

    $salaryModel = SalaryModel::factory()->for($year, 'financialYear')->create();

    $goal = Goal::factory()->for($user)->create(['financial_year_id' => $year->id]);

    expect($salaryModel->financialYear->id)->toBe($year->id)
        ->and($salaryModel->planItems()->count())->toBe(0)
        ->and($goal->financialYear?->id)->toBe($year->id)
        ->and($year->salaryModel?->id)->toBe($salaryModel->id);
});

it('gives holdings a starting set of groups', function (): void {
    expect(NetWorthItem::defaultItems())->toHaveCount(5);
});

it('reports whether a year has been set up and has a frozen plan', function (): void {
    $year = FinancialYear::factory()->create();

    expect($year->isSetupComplete())->toBeFalse()
        ->and($year->hasBaseline())->toBeFalse();

    $setUp = FinancialYear::factory()->setUp()->forYear(2029)->create();

    expect($setUp->isSetupComplete())->toBeTrue();
});

it('refuses an expense filed under an income category', function (): void {
    [$user] = userWithYear();

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Rent',
            'type' => 'expense',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'category_id' => $salary->id,
            'amount' => '700,00',
        ])
        ->assertSessionHasErrors('category_id');
});

it("offers only categories of the step's own type", function (string $step, string $offered, string $withheld): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => $step]))
        ->assertOk()
        ->assertInertia(function ($page) use ($offered, $withheld): void {
            $names = array_column($page->toArray()['props']['categories'], 'name');

            expect($names)->toContain($offered)->not->toContain($withheld);
        });
})->with([
    'income offers income only' => ['income', 'Salary', 'Housing'],
    'expenses offer expenses only' => ['expenses', 'Housing', 'Salary'],
]);

it('shows the salary arrangement once it exists', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)->patch(route('salary-model.update', ['year' => 2027]), [
        'base_amount' => '1.800,00',
        'payments' => SalaryModel::defaultPayments(),
    ]);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'income']))
        ->assertInertia(fn ($page) => $page->where('salaryModel.baseAmountCents', 180_000));

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => 'income']))
        ->assertInertia(fn ($page) => $page->where('salaryModel.baseAmountCents', 180_000));
});

it("totals a category's planned months in the grid", function (): void {
    [$user, $year] = userWithYear();

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();

    resolve(UpdateBudgetCell::class)->handle(
        $year,
        $housing,
        4,
        Money::fromCents(60_000),
    );

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.months.4', 60_000)
            ->where('rows.0.annualCents', 60_000)
            ->where('footerAnnualCents', 60_000));
});

it('counts a step as done from what is planned, and irregular no longer among them', function (): void {
    [$user, $year] = userWithYear();

    // Irregular costs left the wizard for the plan tab that can also edit and remove
    // them, so planning one marks no wizard step done (YEAR-04).
    PlanItem::factory()->for($year, 'financialYear')->irregular()->create([
        'category_id' => $user->categories()->where('name', 'Holidays')->firstOrFail()->id,
    ]);

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => 'expenses']))
        ->assertInertia(function ($page): void {
            expect($page->toArray()['props']['completedSteps'])
                ->not->toContain('irregular')
                ->not->toContain('expenses');
        });

    PlanItem::factory()->for($year, 'financialYear')->create([
        'type' => TransactionType::Expense,
        'kind' => PlanItemKind::Recurring,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
    ]);

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => 'expenses']))
        ->assertInertia(function ($page): void {
            expect($page->toArray()['props']['completedSteps'])->toContain('expenses');
        });
});

it('treats a missing category as nothing to check', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Rent',
            'type' => 'expense',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'amount' => '700,00',
        ])
        // The category is required, so it fails there rather than on the type check.
        ->assertSessionHasErrors('category_id');
});

it('treats holdings that are not a list as nothing to save', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->from(route('year-setup.show', ['year' => 2027, 'step' => 'opening']))
        ->patch(route('opening-position.update', ['year' => 2027]), ['holdings' => 'nonsense'])
        ->assertSessionHasErrors('holdings');
});

it("hides another user's plan item behind a 404", function (string $ability): void {
    [, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => Category::factory()->for($year->user)->create()->id,
    ]);

    $intruder = User::factory()->admin()->create();

    $response = Gate::forUser($intruder)->inspect($ability, $item);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
})->with(['view', 'update', 'delete']);

it('lets the owner see and edit their own plan item', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $user->categories()->firstOrFail()->id,
        'source' => PlanItemSource::Manual,
    ]);

    expect(Gate::forUser($user)->allows('view', $item))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $item))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $item))->toBeTrue();
});

it('skips a payment whose category no longer exists', function (): void {
    [$user, $year] = userWithYear();

    // A category can only be deactivated, not deleted — but the salary arrangement looks
    // its categories up by key, so a missing key must simply skip that payment rather
    // than fail.
    $user->categories()->where('system_key', Category::KEY_EASTER_BONUS)
        ->update(['system_key' => null]);

    resolve(SaveSalaryModel::class)->handle(
        $year,
        Money::fromCents(180_000),
        SalaryModel::defaultPayments(),
    );

    expect($year->planItems()->pluck('name')->all())
        ->toBe(['Salary', 'Christmas Bonus', 'Vacation Allowance']);
});

it("changes a plan item's category without touching its months", function (): void {
    [$user, $year] = userWithYear();

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();
    $utilities = $user->categories()->where('name', 'Utilities')->firstOrFail();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $housing->id,
        'source' => PlanItemSource::Manual,
    ]);

    resolve(SyncPlanItemAmounts::class)->handle($item, [
        1 => Money::fromCents(50_000),
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'type' => 'expense',
            'category_id' => $utilities->id,
        ])
        ->assertSessionHasNoErrors();

    expect($item->refresh()->category_id)->toBe($utilities->id)
        ->and($item->amounts()->where('month', 1)->firstOrFail()->amount_cents->cents)->toBe(50_000);
});

it("rebuilds a plan item's months when an amount is supplied", function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $user->categories()->where('name', 'Housing')->firstOrFail()->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'amount' => '250,00',
        ])
        ->assertSessionHasNoErrors();

    expect((int) $item->amounts()->sum('amount_cents'))->toBe(300_000);
});

it('refuses to move a plan item onto a category of the wrong type', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $user->categories()->where('name', 'Housing')->firstOrFail()->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'type' => 'expense',
            'category_id' => $user->categories()->where('name', 'Salary')->firstOrFail()->id,
        ])
        ->assertSessionHasErrors('category_id');
});

it('rejects an edited amount that is not a number', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $user->categories()->where('name', 'Housing')->firstOrFail()->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'amount' => 'a lot',
        ])
        ->assertSessionHasErrors('amount');
});

it('edits a plan item into chosen months', function (): void {
    [$user, $year] = userWithYear();

    $item = PlanItem::factory()->for($year, 'financialYear')->irregular()->create([
        'category_id' => $user->categories()->where('name', 'Car Expenses')->firstOrFail()->id,
        'frequency' => Frequency::Custom,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'irregular']))
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'amount' => '120,00',
            'custom_months' => [2, 8],
        ])
        ->assertSessionHasNoErrors();

    expect((int) $item->amounts()->sum('amount_cents'))->toBe(24_000);
});
