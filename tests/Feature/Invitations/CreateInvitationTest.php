<?php

declare(strict_types=1);

use App\Actions\CreateInvitation;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\Notification;

it('stores only a hash of the token', function (): void {
    Notification::fake();

    ['invitation' => $invitation, 'token' => $token] = resolve(CreateInvitation::class)
        ->handle('invitee@example.test');

    expect(mb_strlen($token))->toBe(64)
        ->and($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->token_hash)->not->toBe($token);
});

it('expires the link after the configured number of days', function (): void {
    Notification::fake();

    config()->set('invitations.expires_after_days', 3);

    ['invitation' => $invitation] = resolve(CreateInvitation::class)
        ->handle('invitee@example.test');

    // The column has second precision, so compare formatted values rather than instants.
    expect($invitation->expires_at->toDateTimeString())
        ->toBe(now()->addDays(3)->toDateTimeString());
});

it('emails the invited address', function (): void {
    Notification::fake();

    ['invitation' => $invitation] = resolve(CreateInvitation::class)
        ->handle('invitee@example.test');

    Notification::assertSentTo($invitation, InvitationIssued::class);
});

it('revokes any invitation already pending for the same address', function (): void {
    Notification::fake();

    $first = Invitation::factory()->create(['email' => 'invitee@example.test']);

    resolve(CreateInvitation::class)->handle('invitee@example.test');

    expect($first->refresh()->revoked_at)->not->toBeNull()
        ->and(Invitation::query()->where('email', 'invitee@example.test')->count())->toBe(2)
        ->and(Invitation::pending()->where('email', 'invitee@example.test')->count())->toBe(1);
});

it('records the inviter and the admin grant', function (): void {
    Notification::fake();

    $inviter = User::factory()->admin()->create();

    ['invitation' => $invitation] = resolve(CreateInvitation::class)
        ->handle('invitee@example.test', $inviter, true);

    expect($invitation->invited_by)->toBe($inviter->id)
        ->and($invitation->is_admin)->toBeTrue();
});
