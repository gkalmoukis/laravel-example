<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;

it('shows the acceptance form with the invited address', function (): void {
    Invitation::factory()->withToken('valid-token')->create(['email' => 'invitee@example.test']);

    $this->get(route('invitation-acceptance.create', ['token' => 'valid-token']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/accept')
            ->where('email', 'invitee@example.test'));
});

it('creates a verified, logged-in account and lands on the dashboard', function (): void {
    $invitation = Invitation::factory()->withToken('valid-token')->create(['email' => 'invitee@example.test']);

    $this->post(route('invitation-acceptance.store', ['token' => 'valid-token']), [
        'name' => 'Invited Person',
        'password' => 'StrongPassword1234',
        'password_confirmation' => 'StrongPassword1234',
    ])->assertRedirectToRoute('dashboard');

    $user = User::query()->where('email', 'invitee@example.test')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($invitation->refresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('renders the same neutral page for every unusable link', function (string $state): void {
    $factory = Invitation::factory()->withToken('the-token');

    match ($state) {
        'expired' => $factory->expired()->create(),
        'revoked' => $factory->revoked()->create(),
        'accepted' => $factory->accepted()->create(),
        default => null,
    };

    $token = $state === 'unknown' ? 'no-such-token' : 'the-token';

    $this->get(route('invitation-acceptance.create', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('invitations/invalid'));
})->with(['expired', 'revoked', 'accepted', 'unknown']);

it('refuses to create an account from an unusable link', function (string $state): void {
    $factory = Invitation::factory()->withToken('the-token');

    match ($state) {
        'expired' => $factory->expired()->create(),
        'revoked' => $factory->revoked()->create(),
        'accepted' => $factory->accepted()->create(),
        default => null,
    };

    $token = $state === 'unknown' ? 'no-such-token' : 'the-token';

    $this->post(route('invitation-acceptance.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'StrongPassword1234',
        'password_confirmation' => 'StrongPassword1234',
    ])->assertInertia(fn ($page) => $page->component('invitations/invalid'));

    $this->assertGuest();
})->with(['expired', 'revoked', 'accepted', 'unknown']);

it('cannot be used twice', function (): void {
    Invitation::factory()->withToken('valid-token')->create(['email' => 'invitee@example.test']);

    $payload = [
        'name' => 'Invited Person',
        'password' => 'StrongPassword1234',
        'password_confirmation' => 'StrongPassword1234',
    ];

    $this->post(route('invitation-acceptance.store', ['token' => 'valid-token']), $payload)
        ->assertRedirectToRoute('dashboard');

    $this->post(route('logout'));

    $this->post(route('invitation-acceptance.store', ['token' => 'valid-token']), $payload)
        ->assertInertia(fn ($page) => $page->component('invitations/invalid'));

    expect(User::query()->where('email', 'invitee@example.test')->count())->toBe(1);
});

it('enforces the password rules on acceptance', function (): void {
    Invitation::factory()->withToken('valid-token')->create();

    $this->from(route('invitation-acceptance.create', ['token' => 'valid-token']))
        ->post(route('invitation-acceptance.store', ['token' => 'valid-token']), [
            'name' => 'Invited Person',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});
