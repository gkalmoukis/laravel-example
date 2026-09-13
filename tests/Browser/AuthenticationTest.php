<?php

declare(strict_types=1);

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials', function (): void {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'person@example.test',
        'password' => Hash::make('StrongPassword1234'),
    ]);

    FinancialYear::factory()->for($user)->create();

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'StrongPassword1234')
        ->click('@login-button')
        ->assertPathIs('/dashboard')
        ->assertNoJavascriptErrors();
});

it('rejects invalid credentials', function (): void {
    User::factory()->withoutTwoFactor()->create([
        'email' => 'person@example.test',
        'password' => Hash::make('StrongPassword1234'),
    ]);

    visit('/login')
        ->fill('email', 'person@example.test')
        ->fill('password', 'WrongPassword1234')
        ->click('@login-button')
        ->assertPathIs('/login')
        ->assertNoJavascriptErrors();
});

it('challenges for a second factor when two-factor is enabled', function (): void {
    $user = User::factory()->create([
        'email' => 'person@example.test',
        'password' => Hash::make('StrongPassword1234'),
    ]);

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'StrongPassword1234')
        ->click('@login-button')
        ->assertPathBeginsWith('/two-factor')
        ->assertNoJavascriptErrors();
});

it('offers no way to register', function (): void {
    visit('/login')
        ->assertDontSee('Sign up')
        ->assertNoJavascriptErrors();

    visit('/')
        ->assertDontSee('Register')
        ->assertNoJavascriptErrors();
});
