<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\Notification;

it('creates an invitation and prints the link', function (): void {
    Notification::fake();

    $this->artisan('app:invite', ['email' => 'founder@example.test'])
        ->assertSuccessful();

    $invitation = Invitation::query()->where('email', 'founder@example.test')->firstOrFail();

    expect($invitation->is_admin)->toBeFalse()
        ->and($invitation->invited_by)->toBeNull();

    Notification::assertSentTo($invitation, InvitationIssued::class);
});

it('can grant the invite capability', function (): void {
    Notification::fake();

    $this->artisan('app:invite', ['email' => 'founder@example.test', '--admin' => true])
        ->assertSuccessful();

    expect(Invitation::query()->where('email', 'founder@example.test')->firstOrFail()->is_admin)->toBeTrue();
});

it('normalises the address', function (): void {
    Notification::fake();

    $this->artisan('app:invite', ['email' => '  FOUNDER@Example.test '])->assertSuccessful();

    expect(Invitation::query()->where('email', 'founder@example.test')->exists())->toBeTrue();
});

it('refuses an invalid address', function (): void {
    $this->artisan('app:invite', ['email' => 'not-an-email'])->assertFailed();

    expect(Invitation::query()->count())->toBe(0);
});

it('refuses an address that already has an account', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->artisan('app:invite', ['email' => 'taken@example.test'])->assertFailed();

    expect(Invitation::query()->count())->toBe(0);
});
