<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Starts a stopped subscription again (SUB-04).
 *
 * The cancellation date is cleared, so the billing sequence runs uninterrupted again and
 * the plan fills back in.
 */
final readonly class ActivateSubscription
{
    public function __construct(private EnsureSubscriptionSubcategory $subcategory) {}

    public function handle(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $subscription->update(['is_active' => true, 'deactivated_on' => null]);

            // Its subcategory may have been retired along with it, so make sure charges
            // have somewhere to go again.
            $subcategory = $this->subcategory->handle(
                $subscription->user,
                $subscription->category_id,
                $subscription->name,
            );

            $subscription->update(['subcategory_id' => $subcategory->id]);

            return $subscription;
        });
    }
}
