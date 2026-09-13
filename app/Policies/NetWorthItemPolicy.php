<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NetWorthItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A holding belonging to someone else is reported as missing (USR-02, USR-05).
 */
final readonly class NetWorthItemPolicy
{
    public function view(User $user, NetWorthItem $item): Response
    {
        return $this->owns($user, $item);
    }

    public function update(User $user, NetWorthItem $item): Response
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, NetWorthItem $item): Response
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, NetWorthItem $item): Response
    {
        return $user->id === $item->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
