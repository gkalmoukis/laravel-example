<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records something charged on a schedule (SUB-01).
 *
 * The subcategory is created alongside it, so a charge can be filed against this
 * subscription from the moment it exists rather than only after the user thinks to make
 * somewhere to put it (SUB-02).
 */
final readonly class CreateSubscription
{
    public function __construct(
        private EnsureSubscriptionSubcategory $subcategory,
        private SyncSubscriptionPlanItems $planItems,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, CarbonInterface $today): Subscription
    {
        return DB::transaction(function () use ($user, $attributes, $today): Subscription {
            $subscription = $user->subscriptions()->create([
                ...$attributes,
                'is_active' => true,
                'deactivated_on' => null,
            ]);

            $subcategory = $this->subcategory->handle(
                $user,
                $subscription->category_id,
                $subscription->name,
            );

            $subscription->update(['subcategory_id' => $subcategory->id]);

            $this->planItems->forUser($user, $today);

            return $subscription;
        });
    }
}
