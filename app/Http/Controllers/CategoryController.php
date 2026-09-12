<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCategory;
use App\Actions\DeactivateCategory;
use App\Actions\UpdateCategory;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CategoryController
{
    public function index(#[CurrentUser] User $user): Response
    {
        // Eager loaded because strict models forbid lazy loading, and the tree renders
        // every child.
        $categories = $user->categories()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/categories', [
            'categories' => $categories
                ->map(fn (Category $category): array => [
                    ...$this->present($category),
                    'children' => $category->children
                        ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                        ->map(fn (Category $child): array => $this->present($child))
                        ->values()
                        ->all(),
                ])
                ->all(),
        ]);
    }

    public function store(StoreCategoryRequest $request, #[CurrentUser] User $user, CreateCategory $action): RedirectResponse
    {
        $action->handle($user, $request->validated(), $request->parentCategory());

        return to_route('categories.index')->with('status', 'Category added.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): RedirectResponse
    {
        Gate::authorize('update', $category);

        $action->handle($category, $request->validated());

        return to_route('categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category, DeactivateCategory $action): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $action->handle($category);

        return to_route('categories.index')->with('status', 'Category deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'type' => $category->type->value,
            'isActive' => $category->is_active,
            'isEssential' => $category->is_essential,
            'isIrregular' => $category->is_irregular,
            'isSystem' => $category->isSystem(),
            'sortOrder' => $category->sort_order,
        ];
    }
}
