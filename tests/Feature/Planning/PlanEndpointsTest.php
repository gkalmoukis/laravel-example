<?php

declare(strict_types=1);
use App\Actions\ProvisionUserDefaults;
use App\Enums\NetWorthItemKind;
use App\Enums\PlanItemSource;
use App\Models\PlanItem;
use App\Models\SalaryModel;
use App\Models\User;

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
})->with(['opening', 'income', 'expenses']);

it('rejects a step that does not exist', function (string $step): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => $step]))
        ->assertNotFound();
    // Setup is three steps now. Irregular costs and goals are edited on their own
    // screens, and the review step's job — showing what is about to be frozen — is done
    // by the plan itself (YEAR-04).
})->with(['nonsense', 'irregular', 'goals', 'review']);

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

it('marks the opening step done once there is something in it', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => 'opening']))
        ->assertInertia(fn ($page) => $page->where('completedSteps', []));

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    $this->actingAs($user)->patch(route('opening-position.update', ['year' => 2027]), [
        'holdings' => [['id' => $cash->id, 'amount' => '5.000,00']],
    ]);

    // The wizard resumes at the first unfinished step, so which steps count as done is
    // what decides where a returning user lands (YEAR-04).
    $this->actingAs($user)
        ->get(route('year-setup.show', ['year' => 2027, 'step' => 'opening']))
        ->assertInertia(fn ($page) => $page->where('completedSteps', ['opening']));
});

it('carries the categories every editable tab needs', function (string $tab): void {
    [$user] = userWithYear();

    // The tabs could only ever list plan items because nothing was passed to build a
    // form with. Both editable tabs get their own side of the taxonomy (INC-01, IRR-01).
    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => $tab]))
        ->assertInertia(function ($page): void {
            $categories = $page->toArray()['props']['categories'];

            expect($categories)->not->toBe([]);
        });
})->with(['income', 'irregular']);

it('carries what an item needs to be edited back into a form', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Insurance',
            'type' => 'expense',
            'kind' => 'irregular',
            'frequency' => 'annual',
            'start_month' => 4,
            'category_id' => $category->id,
            'amount' => '300,00',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'irregular']))
        ->assertInertia(function ($page): void {
            $item = collect($page->toArray()['props']['items'])
                ->firstWhere('name', 'Insurance');

            // The form reads the amount back out of the month it lands in rather than
            // dividing the annual figure, so every frequency round-trips exactly.
            expect($item['categoryId'])->not->toBeNull()
                ->and($item['startMonth'])->toBe(4)
                ->and($item['allocation'])->toBe('lump_sum')
                ->and($item['months'][4])->toBe(30_000)
                ->and($item['isManual'])->toBeTrue();
        });
});

it('changes a plan item from the tab it is listed on', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Salary')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Freelance',
            'type' => 'income',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'category_id' => $category->id,
            'amount' => '400,00',
        ])
        ->assertSessionHasNoErrors();

    $item = $year->planItems()->where('name', 'Freelance')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('plan-items.update', ['year' => 2027, 'planItem' => $item->id]), [
            'name' => 'Freelance work',
            'type' => 'income',
            'kind' => 'recurring',
            'frequency' => 'monthly',
            'start_month' => 1,
            'category_id' => $category->id,
            'amount' => '500,00',
        ])
        ->assertSessionHasNoErrors();

    $item->refresh();

    expect($item->name)->toBe('Freelance work')
        ->and((int) $item->amounts()->sum('amount_cents'))->toBe(600_000);
});

it('removes a plan item from the tab it is listed on', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->post(route('plan-items.store', ['year' => 2027]), [
            'name' => 'Holiday',
            'type' => 'expense',
            'kind' => 'irregular',
            'frequency' => 'annual',
            'start_month' => 7,
            'category_id' => $category->id,
            'amount' => '900,00',
        ])
        ->assertSessionHasNoErrors();

    $item = $year->planItems()->where('name', 'Holiday')->firstOrFail();

    $this->actingAs($user)
        ->delete(route('plan-items.destroy', ['year' => 2027, 'planItem' => $item->id]))
        ->assertSessionHasNoErrors();

    expect($year->planItems()->where('name', 'Holiday')->exists())->toBeFalse();
});

it('says why a cell could not be saved rather than failing silently', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    PlanItem::factory()->count(2)->for($year, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Manual,
    ]);

    // The grid PATCHed on blur and dropped this on the floor; the message has to reach
    // the page for the cell to be able to show it (BUD-03).
    $response = $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('budget-cell.update', ['year' => 2027]), [
            'category_id' => $category->id,
            'month' => 3,
            'amount' => '450,00',
        ]);

    $response->assertSessionHasErrors('amount');

    $message = session('errors')?->get('amount')[0] ?? '';

    expect($message)->not->toBe('');
});

it('refuses an amount that is not a number and says so', function (): void {
    [$user] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->from(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->patch(route('budget-cell.update', ['year' => 2027]), [
            'category_id' => $category->id,
            'month' => 3,
            'amount' => 'not a number',
        ])
        ->assertSessionHasErrors('amount');
});

it('marks a row with several planned items as one the grid cannot edit', function (): void {
    [$user, $year] = userWithYear();

    $category = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    PlanItem::factory()->count(2)->for($year, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'expenses']))
        ->assertInertia(function ($page) use ($category): void {
            $row = collect($page->toArray()['props']['rows'])
                ->firstWhere('categoryId', $category->id);

            expect($row['isEditable'])->toBeFalse()
                ->and($row['itemCount'])->toBe(2);
        });
});
