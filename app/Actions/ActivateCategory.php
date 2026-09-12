<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class ActivateCategory
{
    /**
     * Reactivating a subcategory reactivates its parent as well, since a child is not
     * reachable in a picker without one.
     */
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->update(['is_active' => true]);

            $category->parent?->update(['is_active' => true]);
        });
    }
}
