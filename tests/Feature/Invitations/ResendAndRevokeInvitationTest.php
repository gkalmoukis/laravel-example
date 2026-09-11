<?php

declare(strict_types=1);

use App\Actions\ResendInvitation;
use App\Actions\RevokeInvitation;
use App\Models\Invitation;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\Notification;

it('issues a new token when resending, invalidating the previous link', function (): void {
    Notification::fake();

    $invitation = Invitation::factory()->withToken('original-token')->create();

    ['token' => $token] = resolve(ResendInvitation::class)->handle($invitation);

    expect($token)->not->toBe('original-token')
        ->and($invitation->refresh()->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->token_hash)->not->toBe(hash('sha256', 'original-token'));

    Notification::assertSentTo($invitation, InvitationIssued::class);
});

it('extends the expiry when resending', function (): void {
    Notification::fake();

    $invitation = Invitation::factory()->expired()->create();

    resolve(ResendInvitation::class)->handle($invitation);

    expect($invitation->refresh()->isPending())->toBeTrue();
});

it('makes a revoked link unusable immediately', function (): void {
    $invitation = Invitation::factory()->create();

    resolve(RevokeInvitation::class)->handle($invitation);

    expect($invitation->refresh()->revoked_at)->not->toBeNull()
        ->and($invitation->isPending())->toBeFalse();
});
