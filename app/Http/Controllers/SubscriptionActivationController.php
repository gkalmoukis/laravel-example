<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ActivateSubscription;
use App\Actions\DeactivateSubscription;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Stopping a subscription, and starting it again (SUB-04).
 */
final readonly class SubscriptionActivationController
{
    public function store(Subscription $subscription, #[CurrentUser] User $user, ActivateSubscription $action): RedirectResponse
    {
        Gate::authorize('update', $subscription);

        $action->handle($subscription, $user->today());

        return back()->with('status', 'Subscription started again.');
    }

    public function destroy(Subscription $subscription, #[CurrentUser] User $user, DeactivateSubscription $action): RedirectResponse
    {
        Gate::authorize('delete', $subscription);

        // Stopped today, in the user's own timezone: months after this are no longer
        // charged, months before it still were.
        $action->handle($subscription, $user->today());

        return back()->with('status', 'Subscription stopped.');
    }
}
