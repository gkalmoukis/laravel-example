<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Stops a subscription from the day it was cancelled (SUB-04).
 *
 * Deactivated rather than deleted: what was paid up to now is part of the year, and the
 * plan should keep showing it. The date matters — months after it are no longer charged,
 * months before it still were.
 */
final readonly class DeactivateSubscription
{
    public function __construct(private SyncSubscriptionPlanItems $planItems) {}

    public function handle(Subscription $subscription, CarbonInterface $on): Subscription
    {
        return DB::transaction(function () use ($subscription, $on): Subscription {
            $subscription->update([
                'is_active' => false,
                'deactivated_on' => $on->toDateString(),
            ]);

            $this->planItems->forUser($subscription->user, $on);

            return $subscription;
        });
    }
}
