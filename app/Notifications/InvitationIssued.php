<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * The invitation email. Queued, so a slow mail provider never blocks the request that
 * issued the invitation (INV-05, ARCH-10).
 */
final class InvitationIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[SensitiveParameter]
        private readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        assert($notifiable instanceof Invitation);

        $days = config()->integer('invitations.expires_after_days');

        return (new MailMessage)
            ->subject(sprintf('You have been invited to %s', config()->string('app.name')))
            ->line(sprintf('You have been invited to join %s.', config()->string('app.name')))
            ->action('Accept invitation', route('invitation-acceptance.create', ['token' => $this->token]))
            ->line(sprintf('This link can be used once and expires in %d days.', $days))
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
