<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MoveSubcategory;
use App\Http\Requests\UpdateSubcategoryParentRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class SubcategoryParentController
{
    public function update(UpdateSubcategoryParentRequest $request, Category $category, MoveSubcategory $action): RedirectResponse
    {
        Gate::authorize('update', $category);

        $action->handle($category, $request->newParent(), $request->boolean('update_existing', true));

        return to_route('categories.index')->with('status', 'Subcategory moved.');
    }
}
