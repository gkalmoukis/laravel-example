<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * See AccountPolicy: another user's record is reported as missing, never as forbidden
 * (USR-02, USR-05).
 */
final readonly class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): Response
    {
        return $this->owns($user, $subscription);
    }

    public function update(User $user, Subscription $subscription): Response
    {
        return $this->owns($user, $subscription);
    }

    /**
     * Subscriptions are deactivated rather than removed, so this guards deactivation: the
     * plan items and transactions already filed against one have to keep resolving.
     */
    public function delete(User $user, Subscription $subscription): Response
    {
        return $this->owns($user, $subscription);
    }

    private function owns(User $user, Subscription $subscription): Response
    {
        return $user->id === $subscription->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
