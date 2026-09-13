<?php

declare(strict_types=1);

use App\Actions\CreateGoal;
use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\User;
use App\ValueObjects\Money;

/**
 * @return array<string, mixed>
 */
function goalPayload(array $overrides = []): array
{
    return [
        'name' => 'New bike',
        'type' => GoalType::Purchase->value,
        'target_amount' => '1.500,00',
        'current_amount' => '200,00',
        'monthly_contribution' => '100,00',
        'target_date' => null,
        ...$overrides,
    ];
}

function userGoal(User $user, string $name): Goal
{
    return $user->goals()->where('name', $name)->firstOrFail();
}

it('lists the goals the user is working on', function (): void {
    $user = planningUser();

    Goal::factory()->for($user)->create([
        'type' => GoalType::Investment,
        'name' => 'Shares',
        'target_amount_cents' => Money::fromCents(500_000),
        'current_amount_cents' => Money::fromCents(100_000),
    ]);

    $this->actingAs($user)
        ->get(route('goals.index'))
        ->assertInertia(function ($page): void {
            $page->component('goals/index');

            $goals = collect($page->toArray()['props']['goals']);
            $shares = $goals->firstWhere('name', 'Shares');

            expect($shares['currentCents'])->toBe(100_000)
                ->and($shares['remainingCents'])->toBe(400_000)
                // Every account has an emergency fund goal (USR-04).
                ->and($goals->pluck('type'))->toContain(GoalType::EmergencyFund->value);
        });
});

it('adds a goal', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload())
        ->assertSessionHasNoErrors();

    $goal = userGoal($user, 'New bike');

    expect($goal->type)->toBe(GoalType::Purchase)
        ->and($goal->target_amount_cents?->cents)->toBe(150_000)
        ->and($goal->current_amount_cents->cents)->toBe(20_000)
        ->and($goal->monthly_contribution_cents?->cents)->toBe(10_000);
});

it('refuses a second emergency fund', function (): void {
    $user = planningUser();

    // One per account, provisioned; a second would make "the" fund ambiguous (GOAL-01).
    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload(['type' => GoalType::EmergencyFund->value]))
        ->assertSessionHasErrors('type');

    expect($user->goals()->where('type', GoalType::EmergencyFund)->count())->toBe(1);
});

it('needs to know which year a year end balance is for', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload([
            'type' => GoalType::YearEndBalance->value,
            'financial_year_id' => null,
        ]))
        ->assertSessionHasErrors('financial_year_id');
});

it('keeps the year a year end balance was set against', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload([
            'name' => 'End of 2027',
            'type' => GoalType::YearEndBalance->value,
            'financial_year_id' => $year->id,
        ]))
        ->assertSessionHasNoErrors();

    expect(userGoal($user, 'End of 2027')->financial_year_id)->toBe($year->id);
});

it('ties no other kind of goal to a year', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)->post(route('goals.store'), goalPayload([
        'financial_year_id' => $year->id,
    ]));

    // A bike is not measured against a financial year.
    expect(userGoal($user, 'New bike')->financial_year_id)->toBeNull();
});

it('refuses another user year', function (): void {
    $user = planningUser();
    [$other, $otherYear] = userWithYear();

    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload([
            'type' => GoalType::YearEndBalance->value,
            'financial_year_id' => $otherYear->id,
        ]))
        ->assertSessionHasErrors('financial_year_id');

    expect($other->goals()->count())->toBe(1);
});

it('refuses an amount that is not a number', function (string $field): void {
    $user = planningUser();

    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload([$field => 'quite a lot']))
        ->assertSessionHasErrors([$field => 'Enter an amount like 1.234,56.']);
})->with(['target_amount', 'current_amount', 'monthly_contribution']);

it('needs a name and a target', function (string $field): void {
    $user = planningUser();

    $this->actingAs($user)
        ->post(route('goals.store'), goalPayload([$field => '']))
        ->assertSessionHasErrors($field);
})->with(['name', 'target_amount']);

it('updates a goal', function (): void {
    $user = planningUser();

    $goal = Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'Bike',
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::zero(),
    ]);

    $this->actingAs($user)
        ->patch(route('goals.update', $goal), [
            'name' => 'Better bike',
            'target_amount' => '2.000,00',
            'current_amount' => '500,00',
            'monthly_contribution' => '150,00',
            'target_date' => '2028-06-01',
        ])
        ->assertSessionHasNoErrors();

    $goal->refresh();

    expect($goal->name)->toBe('Better bike')
        ->and($goal->target_amount_cents?->cents)->toBe(200_000)
        ->and($goal->current_amount_cents->cents)->toBe(50_000)
        ->and($goal->target_date?->toDateString())->toBe('2028-06-01');
});

it('ignores an amount typed against a goal it keeps up to date itself', function (): void {
    [$user, $year] = userWithYear();

    $goal = $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();

    $this->actingAs($user)
        ->patch(route('goals.update', $goal), [
            'name' => 'Emergency fund',
            'target_amount' => '5.000,00',
            'current_amount' => '9.999,00',
            'monthly_contribution' => '',
            'target_date' => null,
        ])
        ->assertSessionHasNoErrors();

    // Typed figures on a derived goal would be overwritten by the next snapshot, so they
    // are not accepted at all (GOAL-03).
    expect($goal->refresh()->current_amount_cents->cents)->toBe(0)
        ->and($goal->name)->toBe('Emergency fund');
});

it('archives a goal and brings it back', function (): void {
    $user = planningUser();

    $goal = Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'Sofa',
    ]);

    $this->actingAs($user)
        ->post(route('goal-archive.store', $goal))
        ->assertSessionHasNoErrors();

    expect($goal->refresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('goal-archive.destroy', $goal))
        ->assertSessionHasNoErrors();

    expect($goal->refresh()->archived_at)->toBeNull();
});

it('refuses to archive the emergency fund', function (): void {
    $user = planningUser();

    $goal = $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();

    $this->actingAs($user)
        ->post(route('goal-archive.store', $goal))
        ->assertForbidden();

    expect($goal->refresh()->archived_at)->toBeNull();
});

it('keeps archived goals out of the list until asked', function (): void {
    $user = planningUser();

    $goal = Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'Sofa',
    ]);

    $this->actingAs($user)->post(route('goal-archive.store', $goal));

    $this->actingAs($user)
        ->get(route('goals.index'))
        ->assertInertia(fn ($page) => $page
            ->where('archivedCount', 1)
            ->has('archived', 0)
            ->where('showArchived', false));

    $this->actingAs($user)
        ->get(route('goals.index', ['archived' => '1']))
        ->assertInertia(fn ($page) => $page
            ->where('showArchived', true)
            ->has('archived', 1)
            ->where('archived.0.name', 'Sofa'));
});

it('offers the years a year end balance can be set against', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->get(route('goals.index'))
        ->assertInertia(fn ($page) => $page
            ->where('years.0.id', $year->id)
            ->where('years.0.year', 2027));
});

it('needs a signed-in user', function (): void {
    $this->get(route('goals.index'))->assertRedirect(route('login'));
});

it('refuses an amount that is not a number on an update too', function (string $field): void {
    $user = planningUser();

    $goal = Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'Bike',
        'target_amount_cents' => Money::fromCents(100_000),
    ]);

    $payload = [
        'name' => 'Bike',
        'target_amount' => '1.000,00',
        'current_amount' => '',
        'monthly_contribution' => '',
        $field => 'quite a lot',
    ];

    $this->actingAs($user)
        ->patch(route('goals.update', $goal), $payload)
        ->assertSessionHasErrors([$field => 'Enter an amount like 1.234,56.']);

    expect($goal->refresh()->name)->toBe('Bike');
})->with(['target_amount', 'current_amount', 'monthly_contribution']);

it('adds a year end balance with no year named at all', function (): void {
    $user = planningUser();

    // The request refuses this, so the Action's own fallback is reached only when it is
    // called directly — by a command or a seeder, which have no form behind them.
    $goal = resolve(CreateGoal::class)->handle($user, [
        'name' => 'Loose end',
        'type' => GoalType::YearEndBalance->value,
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::zero(),
    ]);

    expect($goal->financial_year_id)->toBeNull();
});

it('adds a goal with no type given', function (): void {
    $user = planningUser();

    $goal = resolve(CreateGoal::class)->handle($user, [
        'name' => 'Unclassified',
        'type' => GoalType::Other->value,
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::zero(),
        'financial_year_id' => 999,
    ]);

    // Only a year end balance keeps a year; everything else drops it.
    expect($goal->financial_year_id)->toBeNull();
});
