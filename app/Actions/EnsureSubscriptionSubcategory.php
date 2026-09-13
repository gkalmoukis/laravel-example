<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use App\Models\User;

/**
 * Gives a subscription a subcategory of its own name (SUB-02).
 *
 * So a Netflix charge can be recorded against Subscriptions › Netflix rather than lost in
 * a single undifferentiated Subscriptions total. Reused by name rather than created each
 * time: renaming a subscription back and forth should not leave a trail of categories.
 */
final readonly class EnsureSubscriptionSubcategory
{
    public function handle(User $user, int $categoryId, string $name): Category
    {
        $parent = $user->categories()->whereKey($categoryId)->firstOrFail();

        $existing = $user->categories()
            ->where('parent_id', $parent->id)
            ->where('name', $name)
            ->first();

        if ($existing instanceof Category) {
            // Already deactivated by an earlier deactivation of this subscription, so
            // bring it back rather than leaving the charge nowhere to go.
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing;
        }

        $highestSortOrder = $user->categories()->where('parent_id', $parent->id)->max('sort_order');

        return $user->categories()->create([
            'parent_id' => $parent->id,
            'type' => $parent->type,
            'name' => $name,
            'is_active' => true,
            'sort_order' => is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0,
        ]);
    }
}
