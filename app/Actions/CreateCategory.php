<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;

final readonly class CreateCategory
{
    /**
     * A subcategory always inherits its parent's type, so the two can never disagree
     * (CAT-02). Only top-level categories choose their own.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, ?Category $parent = null): Category
    {
        $siblings = $user->categories()->where('parent_id', $parent?->id);
        $highestSortOrder = $siblings->max('sort_order');

        return $user->categories()->create([
            ...$attributes,
            'parent_id' => $parent?->id,
            'type' => $parent instanceof Category ? $parent->type : $attributes['type'],
            // Flags only mean something for expenses (CAT-06).
            'is_essential' => $this->isExpense($parent, $attributes) && ($attributes['is_essential'] ?? false),
            'is_irregular' => $this->isExpense($parent, $attributes) && ($attributes['is_irregular'] ?? false),
            'sort_order' => is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isExpense(?Category $parent, array $attributes): bool
    {
        $type = $parent instanceof Category ? $parent->type : ($attributes['type'] ?? null);

        return $type === TransactionType::Expense || $type === TransactionType::Expense->value;
    }
}
