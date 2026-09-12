<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateCategory
{
    /**
     * Categories are deactivated, never deleted, so historical records stay valid and
     * readable (CAT-04). Deactivating a parent deactivates its subcategories too,
     * otherwise a child would remain selectable without its parent.
     */
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->update(['is_active' => false]);

            $category->children()->update(['is_active' => false]);
        });
    }
}
