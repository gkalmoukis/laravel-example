<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\ProvisionUserDefaults;
use App\Enums\NetWorthItemKind;
use App\Enums\PlanItemSource;
use App\Models\PlanItem;
use App\Models\SalaryModel;
use App\Models\User;

function userWithYear(int $year = 2027): array
{
    $user = User::factory()->create();

    resolve(ProvisionUserDefaults::class)->handle($user);

    return [$user, resolve(CreateFinancialYear::class)->handle($user, $year)];
}

it('creates an empty year and lands on the first wizard step', function (): void {
    $user = User::factory()->create();
    resolve(ProvisionUserDefaults::class)->handle($user);

    $this->actingAs($user)
        ->post(route('financial-years.store'), ['year' => 2027])
        ->assertRedirect(route('year-setup.show', ['year' => 2027, 'step' => 'opening']));

    expect($user->financialYears()->where('year', 2027)->exists())->toBeTrue();
});

it('refuses a second plan for the same year', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->from(route('financial-years.create'))
        ->post(route('financial-years.store'), ['year' => 2027])
        ->assertSessionHasErrors('year');
});

it('refuses a year outside the offered span', function (int $year): void {
    $user = User::factory()->create();
    resolve(ProvisionUserDefaults::class)->handle($user);

    $this->actingAs($user)
        ->from(route('financial-years.create'))
        ->post(route('financial-years.store'), ['year' => $year])
        ->assertSessionHasErrors('year');
})->with([1999, 2040]);

it('copies a year when asked', function (): void {
    [$user, $source] = userWithYear();

    $this->actingAs($user)
        ->post(route('financial-years.store'), ['year' => 2028, 'copy_from_id' => $source->id])
        ->assertRedirect(route('year-setup.show', ['year' => 2028, 'step' => 'opening']));

    expect($user->financialYears()->where('year', 2028)->firstOrFail()->copied_from_id)->toBe($source->id);
});

it('shows each wizard step', function (string $step): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => $step]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('years/setup')->where('step', $step));
})->with(['opening', 'income', 'expenses', 'irregular', 'goals', 'review']);

it('rejects a step that does not exist', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => 'nonsense']))
        ->assertNotFound();
});

it('saves the opening position from typed amounts', function (): void {
    [$user, $year] = userWithYear();

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    $this->actingAs($user)
        ->from(route('year-setup.show', ['year' => 2027, 'step' => 'opening']))
        ->patch(route('opening-position.update', ['year' => 2027]), [
            'holdings' => [['id' => $cash->id, 'amount' => '1.234,56']],
        ])
        ->assertSessionHasNoErrors();

    expect($year->netWorthSnapshots()->where('net_worth_item_id', $cash->id)->firstOrFail()->value_cents->cents)
        ->toBe(123_456);
});

it('rejects an opening amount that is not a number', function (): void {
    [$user] = userWithYear();

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    $this->actingAs($user)
        ->from(route('year-setup.show', ['year' => 2027, 'step' => 'opening']))
        ->patch(route('opening-position.update', ['year' => 2027]), [
            'holdings' => [['id' => $cash->id, 'amount' => 'lots']],
        ])
        ->assertSessionHasErrors('holdings.0.amount');
});

it('saves the salary arrangement', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->from(route('year-setup.show', ['year' => 2027, 'step' => 'income']))
        ->patch(route('salary-model.update', ['year' => 2027]), [
            'base_amount' => '1.800,00',
            'payments' => SalaryModel::defaultPayments(),
        ])
        ->assertSessionHasNoErrors();

    expect($year->salaryModel()->firstOrFail()->base_amount_cents->cents)->toBe(180_000)
        ->and($year->planItems()->where('source', PlanItemSource::SalaryModel)->count())->toBe(4);
});

it('removes the salary arrangement', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)->patch(route('salary-model.update', ['year' => 2027]), [
        'base_amount' => '1.800,00',
        'payments' => SalaryModel::defaultPayments(),
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'income']))
        ->delete(route('salary-model.destroy', ['year' => 2027]))
        ->assertSessionHasNoErrors();

    expect($year->salaryModel()->exists())->toBeFalse();
});

it('adds a plan item', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->firstOrFail();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Rent',
            'type' => 'expense',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'category_id' => $category->id,
            'amount' => '700,00',
        ])
        ->assertSessionHasNoErrors();

    $item = $year->planItems()->where('name', 'Rent')->firstOrFail();

    expect($item->amounts()->count())->toBe(12)
        ->and((int) $item->amounts()->sum('amount_cents'))->toBe(840_000);
});

it('refuses a category that is not yours', function (): void {
    [$user] = userWithYear();

    $someoneElses = User::factory()->create();
    resolve(ProvisionUserDefaults::class)->handle($someoneElses);
    $theirCategory = $someoneElses->categories()->firstOrFail();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Sneaky',
            'type' => 'expense',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'category_id' => $theirCategory->id,
            'amount' => '10,00',
        ])
        ->assertSessionHasErrors('category_id');
});

it('will not delete an item generated from the salary arrangement', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)->patch(route('salary-model.update', ['year' => 2027]), [
        'base_amount' => '1.800,00',
        'payments' => SalaryModel::defaultPayments(),
    ]);

    $generated = $year->planItems()->where('source', PlanItemSource::SalaryModel)->firstOrFail();

    $this->actingAs($user)
        ->delete(route('plan-items.destroy', ['year' => 2027, 'planItem' => $generated->id]))
        ->assertForbidden();

    expect(PlanItem::query()->whereKey($generated->id)->exists())->toBeTrue();
});

it('edits a budget cell', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->firstOrFail();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('budget-cell.update', ['year' => 2027]), [
            'category_id' => $category->id,
            'month' => 3,
            'amount' => '450,00',
        ])
        ->assertSessionHasNoErrors();

    $item = $year->planItems()->where('category_id', $category->id)->firstOrFail();

    expect($item->name)->toBe('Housing')
        ->and($item->amounts()->where('month', 3)->firstOrFail()->amount_cents->cents)->toBe(45_000);
});

it('reports a cell it cannot edit rather than guessing', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->firstOrFail();

    PlanItem::factory()->count(2)->for($year, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('budget-cell.update', ['year' => 2027]), [
            'category_id' => $category->id,
            'month' => 3,
            'amount' => '450,00',
        ])
        ->assertSessionHasErrors('amount');
});

it('finishes setup and freezes the baseline', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->post(route('year-setup-completion.store', ['year' => 2027]))
        ->assertRedirect(route('plan.show', ['year' => 2027, 'tab' => 'income']));

    expect($year->refresh()->isSetupComplete())->toBeTrue()
        ->and($year->hasBaseline())->toBeTrue();
});

it('re-freezes the baseline when asked', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)->post(route('year-setup-completion.store', ['year' => 2027]));
    $first = $year->refresh()->baseline_captured_at;

    $this->travel(1)->hours();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'income']))
        ->post(route('plan-baseline.store', ['year' => 2027]))
        ->assertSessionHasNoErrors();

    expect($year->refresh()->baseline_captured_at?->greaterThan($first))->toBeTrue();
});

it('shows each plan tab', function (string $tab): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('plan/'.$tab));
})->with(['income', 'expenses', 'irregular', 'opening']);

it("hides another user's year behind a 404", function (): void {
    [, $year] = userWithYear();

    $intruder = User::factory()->admin()->create();

    $this->actingAs($intruder)
        ->get(route('year-setup.show', ['year' => $year->year, 'step' => 'opening']))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('plan.show', ['year' => $year->year, 'tab' => 'income']))
        ->assertNotFound();
});
