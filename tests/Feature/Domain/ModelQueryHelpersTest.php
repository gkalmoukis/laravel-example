<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;

it('offers only active accounts', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Open']);
    Account::factory()->for($user)->inactive()->create(['name' => 'Closed']);

    expect(Account::active()->pluck('name')->all())->toBe(['Open']);
});

it('offers only top-level categories', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create(['name' => 'Parent']);
    Category::factory()->for($user)->childOf($parent)->create(['name' => 'Child']);

    expect(Category::topLevel()->pluck('name')->all())->toBe(['Parent']);
});

it('resolves the default account from preferences', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Everyday']);

    $preference = UserPreference::factory()->for($user)->create([
        'default_account_id' => $account->id,
    ]);

    expect($preference->defaultAccount?->name)->toBe('Everyday');
});

it('has no default account when none is set', function (): void {
    $preference = UserPreference::factory()->for(User::factory())->create();

    expect($preference->defaultAccount)->toBeNull();
});
