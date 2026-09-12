<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;

/*
 * A record belonging to someone else must be indistinguishable from one that does not
 * exist, so 404 rather than 403 (USR-02). An admin gets exactly the same answer: the
 * admin flag grants inviting people and nothing else (USR-05).
 */

it("hides another user's account behind a 404", function (bool $asAdmin, string $method): void {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $intruder = $asAdmin
        ? User::factory()->admin()->create()
        : User::factory()->create();

    $payload = $method === 'patch'
        ? ['name' => 'Stolen', 'type' => 'bank']
        : [];

    $this->actingAs($intruder)
        ->call($method, route('accounts.'.($method === 'patch' ? 'update' : 'destroy'), $account), $payload)
        ->assertNotFound();

    expect($account->refresh()->name)->not->toBe('Stolen')
        ->and($account->is_active)->toBeTrue();
})->with([
    'ordinary user updating' => [false, 'patch'],
    'ordinary user deleting' => [false, 'delete'],
    'admin updating' => [true, 'patch'],
    'admin deleting' => [true, 'delete'],
]);

it("never lists another user's accounts", function (): void {
    $owner = User::factory()->create();
    Account::factory()->for($owner)->count(3)->create();

    $intruder = User::factory()->admin()->create();

    $this->actingAs($intruder)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('accounts', 0));
});
