<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ResendInvitation;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class InvitationResendController
{
    public function store(Invitation $invitation, ResendInvitation $action): RedirectResponse
    {
        Gate::authorize('update', $invitation);

        $action->handle($invitation);

        return to_route('invitations.index')
            ->with('status', 'Invitation sent again.');
    }
}
