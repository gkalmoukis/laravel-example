<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Notifications\InvitationIssued;
use Illuminate\Contracts\Queue\ShouldQueue;

it('is queued so a slow mail provider never blocks the request', function (): void {
    expect(new InvitationIssued('a-token'))
        ->toBeInstanceOf(ShouldQueue::class);
});

it('is delivered by mail to the invited address', function (): void {
    $invitation = Invitation::factory()->create(['email' => 'invitee@example.test']);

    expect(new InvitationIssued('a-token')->via($invitation))->toBe(['mail'])
        ->and($invitation->routeNotificationForMail())->toBe('invitee@example.test');
});

it('links to the acceptance page with the plaintext token', function (): void {
    config()->set('invitations.expires_after_days', 7);

    $invitation = Invitation::factory()->create();

    $mail = new InvitationIssued('a-token')->toMail($invitation);

    expect($mail->subject)->toBe('You have been invited to Fin')
        ->and($mail->actionUrl)->toBe(route('invitation-acceptance.create', ['token' => 'a-token']))
        ->and($mail->actionText)->toBe('Accept invitation')
        ->and($mail->introLines)->toContain('You have been invited to join Fin.')
        ->and($mail->outroLines)->toContain('This link can be used once and expires in 7 days.');
});
