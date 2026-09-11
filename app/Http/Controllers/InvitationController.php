<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateInvitation;
use App\Actions\RevokeInvitation;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class InvitationController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Invitation::class);

        return Inertia::render('invitations/index', [
            'invitations' => Invitation::query()
                ->with('inviter')
                ->latest()
                ->get()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'isAdmin' => $invitation->is_admin,
                    'status' => match (true) {
                        $invitation->accepted_at !== null => 'accepted',
                        $invitation->revoked_at !== null => 'revoked',
                        $invitation->expires_at->isPast() => 'expired',
                        default => 'pending',
                    },
                    'invitedBy' => $invitation->inviter?->name,
                    'expiresAt' => $invitation->expires_at->toIso8601String(),
                    'createdAt' => $invitation->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function store(StoreInvitationRequest $request, #[CurrentUser] User $user, CreateInvitation $action): RedirectResponse
    {
        Gate::authorize('create', Invitation::class);

        $action->handle(
            $request->string('email')->value(),
            $user,
            $request->boolean('is_admin'),
        );

        return to_route('invitations.index')
            ->with('status', 'Invitation sent.');
    }

    public function destroy(Invitation $invitation, RevokeInvitation $action): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $action->handle($invitation);

        return to_route('invitations.index')
            ->with('status', 'Invitation revoked.');
    }
}
