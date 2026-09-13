<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;

it('turns a valid invitation link into a logged-in account', function (): void {
    Invitation::factory()->withToken('a-valid-token')->create([
        'email' => 'invitee@example.test',
    ]);

    visit('/invitations/a-valid-token')
        ->assertValue('email', 'invitee@example.test')
        ->fill('name', 'Invited Person')
        ->fill('password', 'StrongPassword1234')
        ->fill('password_confirmation', 'StrongPassword1234')
        ->click('@accept-invitation-button')
        // A new account has no plan yet, so it starts by making one (YEAR-08).
        ->assertPathIs('/years/create')
        ->assertNoJavascriptErrors();

    expect(User::query()->where('email', 'invitee@example.test')->firstOrFail()->email_verified_at)
        ->not->toBeNull();
});

it('shows the neutral page for an expired link', function (): void {
    Invitation::factory()->expired()->withToken('an-expired-token')->create();

    visit('/invitations/an-expired-token')
        ->assertSee('no longer valid')
        ->assertNoJavascriptErrors();
});

it('lets an admin invite, resend and revoke', function (): void {
    $admin = User::factory()->admin()->withoutTwoFactor()->create();

    $page = $this->actingAs($admin)->visit('/settings/invitations');

    $page->assertSee('Invite someone')
        ->fill('email', 'invitee@example.test')
        ->click('Send invitation')
        ->assertSee('invitee@example.test')
        ->assertSee('Pending')
        ->assertNoJavascriptErrors();

    $page->click('Resend')->assertSee('invitee@example.test')->assertNoJavascriptErrors();

    $page->click('Revoke')
        ->assertSee('Revoke this invitation?')
        ->click('@confirm-revoke-button')
        ->assertSee('Revoked')
        ->assertNoJavascriptErrors();
});

it('hides invitation management from a non-admin', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->visit('/settings/profile')
        ->assertDontSee('Invitations')
        ->assertNoJavascriptErrors();
});
