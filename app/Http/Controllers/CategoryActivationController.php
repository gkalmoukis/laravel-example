<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ActivateCategory;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class CategoryActivationController
{
    public function store(Category $category, ActivateCategory $action): RedirectResponse
    {
        Gate::authorize('update', $category);

        $action->handle($category);

        return to_route('categories.index')->with('status', 'Category reactivated.');
    }
}
