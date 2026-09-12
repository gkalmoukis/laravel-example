<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every finance record belongs to exactly one user. A record owned by someone else is
 * reported as missing rather than forbidden, so its existence is never revealed — not
 * even to an admin, whose only extra capability is inviting people (USR-02, USR-05).
 */
final readonly class AccountPolicy
{
    public function view(User $user, Account $account): Response
    {
        return $this->owns($user, $account);
    }

    public function update(User $user, Account $account): Response
    {
        return $this->owns($user, $account);
    }

    public function delete(User $user, Account $account): Response
    {
        return $this->owns($user, $account);
    }

    private function owns(User $user, Account $account): Response
    {
        return $user->id === $account->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
