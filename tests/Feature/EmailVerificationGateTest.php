<?php

declare(strict_types=1);

use App\Models\User;

it('keeps unverified users out of every application route', function (string $method, string $route): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->call($method, route($route))
        ->assertRedirectToRoute('verification.notice');
})->with([
    ['get', 'dashboard'],
    ['get', 'user-profile.edit'],
    ['get', 'password.edit'],
    ['get', 'appearance.edit'],
    ['get', 'two-factor.show'],
]);

it('lets verified users through', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('keeps the verification routes reachable while unverified', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
});

it('lets an unverified user log out', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});
