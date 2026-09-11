<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('does not expose a registration page', function (): void {
    $this->get('/register')->assertNotFound();
});

it('does not expose a registration endpoint', function (): void {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password1234',
        'password_confirmation' => 'Password1234',
    ])->assertNotFound();

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

it('has no named registration routes', function (): void {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});
