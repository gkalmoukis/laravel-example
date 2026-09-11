<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ResendInvitation
{
    /**
     * Issues a fresh token for an existing invitation, which invalidates the previous
     * link immediately, and emails it again (INV-04).
     *
     * @return array{invitation: Invitation, token: string}
     */
    public function handle(Invitation $invitation): array
    {
        $token = Str::random(64);

        DB::transaction(function () use ($invitation, $token): void {
            $invitation->forceFill([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(config()->integer('invitations.expires_after_days')),
                'revoked_at' => null,
            ])->save();
        });

        $invitation->notify(new InvitationIssued($token));

        return ['invitation' => $invitation, 'token' => $token];
    }
}
