<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use App\Http\Requests\StoreInvitationAcceptanceRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class InvitationAcceptanceController
{
    public function create(string $token): Response
    {
        $invitation = $this->pendingInvitation($token);

        if (! $invitation instanceof Invitation) {
            return $this->neutralError();
        }

        return Inertia::render('invitations/accept', [
            'token' => $token,
            'email' => $invitation->email,
        ]);
    }

    public function store(StoreInvitationAcceptanceRequest $request, string $token, AcceptInvitation $action): RedirectResponse|Response
    {
        $invitation = $this->pendingInvitation($token);

        if (! $invitation instanceof Invitation) {
            return $this->neutralError();
        }

        $user = $action->handle(
            $invitation,
            $request->string('name')->value(),
            $request->string('password')->value(),
        );

        Auth::login($user);

        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * Looks the invitation up by the hash of the supplied token, so the plaintext is
     * never compared against anything stored (INV-03).
     */
    private function pendingInvitation(string $token): ?Invitation
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $invitation?->isPending() === true ? $invitation : null;
    }

    /**
     * Invalid, expired, revoked and already-used links are indistinguishable from each
     * other, so nothing leaks about whether an address was ever invited (INV-07).
     */
    private function neutralError(): Response
    {
        return Inertia::render('invitations/invalid');
    }
}
