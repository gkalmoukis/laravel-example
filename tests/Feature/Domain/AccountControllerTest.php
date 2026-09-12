<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;

it("lists only the signed-in user's accounts", function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Mine']);
    Account::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/accounts')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Mine'));
});

it('adds an account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->post(route('accounts.store'), ['name' => 'Savings', 'type' => 'bank'])
        ->assertRedirectToRoute('accounts.index');

    $account = $user->accounts()->where('name', 'Savings')->firstOrFail();

    expect($account->type)->toBe(AccountType::Bank)
        ->and($account->is_active)->toBeTrue();
});

it('rejects a duplicate name for the same user but allows it across users', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Cash']);

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->post(route('accounts.store'), ['name' => 'Cash', 'type' => 'cash'])
        ->assertSessionHasErrors('name');

    // The same name is fine for a different person.
    $other = User::factory()->create();

    $this->actingAs($other)
        ->from(route('accounts.index'))
        ->post(route('accounts.store'), ['name' => 'Cash', 'type' => 'cash'])
        ->assertSessionHasNoErrors();
});

it('requires a known account type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->post(route('accounts.store'), ['name' => 'Crypto', 'type' => 'not-a-type'])
        ->assertSessionHasErrors('type');
});

it('renames an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Old']);

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->patch(route('accounts.update', $account), ['name' => 'New', 'type' => $account->type->value])
        ->assertRedirectToRoute('accounts.index');

    expect($account->refresh()->name)->toBe('New');
});

it('deactivates rather than deletes', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->delete(route('accounts.destroy', $account))
        ->assertRedirectToRoute('accounts.index');

    expect($account->refresh()->is_active)->toBeFalse()
        ->and(Account::query()->whereKey($account->id)->exists())->toBeTrue();
});

it('reactivates through an update', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->inactive()->create();

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => $account->type->value,
            'is_active' => true,
        ]);

    expect($account->refresh()->is_active)->toBeTrue();
});

it('orders new accounts after the existing ones', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['sort_order' => 7]);

    $this->actingAs($user)->post(route('accounts.store'), ['name' => 'Later', 'type' => 'bank']);

    expect($user->accounts()->where('name', 'Later')->firstOrFail()->sort_order)->toBe(8);
});
