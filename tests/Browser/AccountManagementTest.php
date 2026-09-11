<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/*
 * Browser tests assert through the interface rather than the database. The application
 * runs in a separate process, and under MySQL's REPEATABLE READ the test's own
 * transaction cannot see rows that process commits, so re-reading a model here would
 * return a stale snapshot.
 */

it('resets a password through the emailed link', function (): void {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'person@example.test',
        'password' => Hash::make('OldPassword1234'),
    ]);

    // The stored token is hashed, so mint the one the email would have carried.
    $token = Password::broker()->createToken($user);

    // Landing on the login page is the success path; the failure path throws a
    // validation error and keeps the user on the reset form. That the credentials
    // actually change is asserted in tests/Feature/Controllers/UserPasswordControllerTest.
    visit('/reset-password/'.$token.'?email=person%40example.test')
        ->fill('password', 'BrandNewPassword1234')
        ->fill('password_confirmation', 'BrandNewPassword1234')
        ->click('@reset-password-button')
        ->assertPathIs('/login')
        ->assertNoJavascriptErrors();
});

it('rejects a weak password on the reset form', function (): void {
    $user = User::factory()->withoutTwoFactor()->create(['email' => 'person@example.test']);

    $token = Password::broker()->createToken($user);

    visit('/reset-password/'.$token.'?email=person%40example.test')
        ->fill('password', 'weak')
        ->fill('password_confirmation', 'weak')
        ->click('@reset-password-button')
        // Staying put is the rejection: a successful reset redirects to the login page.
        // The rule's individual clauses are asserted in tests/Feature/PasswordDefaultsTest.
        ->assertPathBeginsWith('/reset-password')
        ->assertNoJavascriptErrors();
});

it('sends the user back to verification after changing their email', function (): void {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'old@example.test',
    ]);

    // Changing the address clears verification, so the verified middleware immediately
    // bounces the user to the verification notice (VER-01, VER-03).
    $this->actingAs($user)
        ->visit('/settings/profile')
        ->fill('email', 'new@example.test')
        ->click('@update-profile-button')
        ->assertPathIs('/verify-email')
        ->assertSee('Verify email')
        ->assertNoJavascriptErrors();
});

it('renders every authenticated page without console or javascript errors', function (string $path): void {
    $user = User::factory()->admin()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->visit($path)
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with([
    '/dashboard',
    '/settings/profile',
    '/settings/password',
    '/settings/appearance',
    '/settings/two-factor',
    '/settings/invitations',
]);
