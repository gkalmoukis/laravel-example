<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Auth\Access\Response;

/**
 * See AccountPolicy: another user's record is reported as missing, never as forbidden
 * (USR-02, USR-05).
 */
final readonly class UserPreferencePolicy
{
    public function view(User $user, UserPreference $preference): Response
    {
        return $this->owns($user, $preference);
    }

    public function update(User $user, UserPreference $preference): Response
    {
        return $this->owns($user, $preference);
    }

    private function owns(User $user, UserPreference $preference): Response
    {
        return $user->id === $preference->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
