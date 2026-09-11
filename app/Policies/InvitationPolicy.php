<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

/**
 * Only admins may manage invitations (INV-01, OD-12). This is the single capability the
 * admin flag grants; it confers no access to other users' data.
 */
final readonly class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return $user->is_admin && $invitation->isPending();
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->is_admin && $invitation->isPending();
    }
}
