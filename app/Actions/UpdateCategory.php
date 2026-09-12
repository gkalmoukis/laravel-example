<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCategory
{
    /**
     * Renames a category, toggles its flags, and moves it up or down among its siblings.
     *
     * The type is never changed here. Once anything references a category, changing its
     * type would silently reclassify that history (CAT-03), and before anything does the
     * user can simply create the category they meant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes): Category {
            $sortOrder = $attributes['sort_order'] ?? null;

            if (is_numeric($sortOrder)) {
                $this->swapWithSiblingAt($category, (int) $sortOrder);
            }

            $isExpense = $category->type === TransactionType::Expense;

            $category->update([
                ...$attributes,
                // Flags only mean something for expenses (CAT-06).
                'is_essential' => $isExpense && ($attributes['is_essential'] ?? $category->is_essential),
                'is_irregular' => $isExpense && ($attributes['is_irregular'] ?? $category->is_irregular),
            ]);

            return $category;
        });
    }

    /**
     * Reordering swaps positions with whichever sibling currently sits at the target, so
     * the list stays a stable permutation rather than drifting.
     */
    private function swapWithSiblingAt(Category $category, int $sortOrder): void
    {
        $sibling = Category::query()
            ->where('user_id', $category->user_id)
            ->where('parent_id', $category->parent_id)
            ->where('sort_order', $sortOrder)
            ->whereKeyNot($category->id)
            ->first();

        $sibling?->update(['sort_order' => $category->sort_order]);
    }
}
