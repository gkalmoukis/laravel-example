<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Category;
use App\Models\Goal;
use App\Models\NetWorthItem;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Gate;

/*
 * Every model has a policy, and each denies access to another user's record as "not
 * found" rather than "forbidden" (USR-02). Goals have no screens until milestone 5, so
 * they are asserted at the policy level here.
 */

it("denies another user's record as missing", function (string $model, string $ability): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->admin()->create();

    $record = match ($model) {
        Account::class => Account::factory()->for($owner)->create(),
        Category::class => Category::factory()->for($owner)->create(),
        Goal::class => Goal::factory()->for($owner)->create(),
        Subscription::class => Subscription::factory()->for($owner)->create([
            'category_id' => Category::factory()->for($owner),
        ]),
        default => UserPreference::factory()->for($owner)->create(),
    };

    $response = Gate::forUser($intruder)->inspect($ability, $record);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
})->with([
    'account view' => [Account::class, 'view'],
    'account update' => [Account::class, 'update'],
    'account delete' => [Account::class, 'delete'],
    'category view' => [Category::class, 'view'],
    'category update' => [Category::class, 'update'],
    'category delete' => [Category::class, 'delete'],
    'preference view' => [UserPreference::class, 'view'],
    'preference update' => [UserPreference::class, 'update'],
    'goal view' => [Goal::class, 'view'],
    'goal update' => [Goal::class, 'update'],
    'goal delete' => [Goal::class, 'delete'],
    'subscription view' => [Subscription::class, 'view'],
    'subscription update' => [Subscription::class, 'update'],
    'subscription delete' => [Subscription::class, 'delete'],
]);

it('allows the owner', function (string $model, string $ability): void {
    $owner = User::factory()->create();

    $record = match ($model) {
        Account::class => Account::factory()->for($owner)->create(),
        Category::class => Category::factory()->for($owner)->create(),
        Goal::class => Goal::factory()->for($owner)->create(),
        Subscription::class => Subscription::factory()->for($owner)->create([
            'category_id' => Category::factory()->for($owner),
        ]),
        default => UserPreference::factory()->for($owner)->create(),
    };

    expect(Gate::forUser($owner)->allows($ability, $record))->toBeTrue();
})->with([
    'account update' => [Account::class, 'update'],
    'category update' => [Category::class, 'update'],
    'preference update' => [UserPreference::class, 'update'],
    'goal update' => [Goal::class, 'update'],
    'subscription update' => [Subscription::class, 'update'],
]);

it('refuses to remove records that must always exist', function (): void {
    $user = User::factory()->create();

    $systemCategory = Category::factory()->for($user)->system(Category::KEY_SUBSCRIPTIONS)->create();
    $emergencyFund = Goal::factory()->for($user)->emergencyFund()->create();

    $categoryResponse = Gate::forUser($user)->inspect('delete', $systemCategory);
    $goalResponse = Gate::forUser($user)->inspect('delete', $emergencyFund);

    // Denied to the owner, but as forbidden rather than missing: the record plainly
    // exists and the message explains why it stays.
    expect($categoryResponse->allowed())->toBeFalse()
        ->and($categoryResponse->status())->not->toBe(404)
        ->and($goalResponse->allowed())->toBeFalse()
        ->and($goalResponse->status())->not->toBe(404);
});

it("hides another user's holding behind a 404", function (string $ability): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->admin()->create();

    $holding = NetWorthItem::factory()->for($owner)->create();

    $response = Gate::forUser($intruder)->inspect($ability, $holding);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
})->with(['view', 'update', 'delete']);

it('lets the owner manage their own holding', function (string $ability): void {
    $owner = User::factory()->create();

    $holding = NetWorthItem::factory()->for($owner)->create();

    expect(Gate::forUser($owner)->allows($ability, $holding))->toBeTrue();
})->with(['view', 'update', 'delete']);
