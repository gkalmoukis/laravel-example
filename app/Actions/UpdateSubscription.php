<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Changes a subscription (SUB-01).
 *
 * A renamed subscription gets a subcategory under its new name, but the old one is left
 * alone: transactions already filed against it are part of what the user recorded, and
 * renaming a category under them would rewrite their history.
 */
final readonly class UpdateSubscription
{
    public function __construct(private EnsureSubscriptionSubcategory $subcategory) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Subscription $subscription, array $attributes): Subscription
    {
        return DB::transaction(function () use ($subscription, $attributes): Subscription {
            $subscription->update($attributes);

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
