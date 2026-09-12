<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class MoveSubcategory
{
    /**
     * Moves a subcategory under a different parent of the same type (CAT-05).
     *
     * The caller says whether records already filed under it should follow. Nothing
     * references categories yet: plan items arrive in milestone 2 and transactions in
     * milestone 3, and each will do its half of the move here. Until then the flag is
     * recorded and has nothing to act on — declining it is what later leaves those
     * transactions carrying a subcategory-parent mismatch.
     */
    public function handle(Category $subcategory, Category $newParent, bool $updateExisting = true): Category
    {
        return DB::transaction(function () use ($subcategory, $newParent, $updateExisting): Category {
            $subcategory->update(['parent_id' => $newParent->id]);

            if ($updateExisting) {
                $this->repointExistingRecords();
            }

            return $subcategory;
        });
    }

    /**
     * Extended by milestone 2 (plan items) and milestone 3 (transactions).
     */
    private function repointExistingRecords(): void
    {
        //
    }
}
