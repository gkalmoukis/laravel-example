<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\FortifyServiceProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

it('requires at least twelve characters', function (): void {
    expectPasswordRejected('Short1Aa');
});

it('requires mixed case', function (): void {
    expectPasswordRejected('alllowercase1234');
});

it('requires a number', function (): void {
    expectPasswordRejected('NoDigitsInHere');
});

it('accepts a password meeting every rule', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);

    $this->actingAs($user)
        ->fromRoute('password.edit')
        ->put(route('password.update'), [
            'current_password' => 'current-password',
            'password' => 'StrongPassword1234',
            'password_confirmation' => 'StrongPassword1234',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('StrongPassword1234', $user->refresh()->password))->toBeTrue();
});

it('checks passwords against known breaches in production only', function (bool $production, bool $expected): void {
    $this->app->detectEnvironment(fn (): string => $production ? 'production' : 'testing');

    new FortifyServiceProvider($this->app)->boot();

    $rule = Password::default();

    expect(new ReflectionProperty($rule, 'uncompromised')->getValue($rule))->toBe($expected);
})->with([
    'production' => [true, true],
    'local' => [false, false],
]);

function expectPasswordRejected(string $password): void
{
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);

    test()->actingAs($user)
        ->fromRoute('password.edit')
        ->put(route('password.update'), [
            'current_password' => 'current-password',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check($password, $user->refresh()->password))->toBeFalse();
}
