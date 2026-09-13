<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Subscription;
use App\Models\User;

/**
 * Which subscription a transaction is paying for, from the subcategory it was filed
 * under (SUB-06).
 *
 * Every subscription keeps a subcategory of its own name, so choosing that subcategory is
 * already saying which subscription this is — the link follows from it rather than asking
 * the user the same question twice.
 */
final readonly class ResolveTransactionSubscription
{
    public function handle(User $user, ?int $subcategoryId): ?int
    {
        if ($subcategoryId === null) {
            return null;
        }

        // A stopped subscription whose name is used again shares its subcategory, so the
        // running one wins and the most recent one settles the rest. A stopped
        // subscription still claims its own charges: a final invoice is still its own.
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('subcategory_id', $subcategoryId)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        return $subscription?->id;
    }
}
