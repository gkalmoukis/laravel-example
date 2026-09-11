<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateInvitation
{
    /**
     * Issues an invitation and emails the link.
     *
     * Any invitation already pending for the address is revoked first, so there is never
     * more than one usable link per email (INV-02, INV-04). Only the token's hash is
     * persisted; the plaintext is returned to the caller and otherwise exists only inside
     * the emailed link (INV-03).
     *
     * @return array{invitation: Invitation, token: string}
     */
    public function handle(string $email, ?User $inviter = null, bool $isAdmin = false): array
    {
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($email, $inviter, $isAdmin, $token): Invitation {
            Invitation::pending()
                ->where('email', $email)
                ->update(['revoked_at' => now()]);

            return Invitation::query()->create([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'is_admin' => $isAdmin,
                'invited_by' => $inviter?->id,
                'expires_at' => now()->addDays(config()->integer('invitations.expires_after_days')),
            ]);
        });

        $invitation->notify(new InvitationIssued($token));

        return ['invitation' => $invitation, 'token' => $token];
    }
}
