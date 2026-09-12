<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSubcategoryParentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],

            // Whether records already filed under this subcategory should follow it
            // (CAT-05).
            'update_existing' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('category');
                assert($category instanceof Category);

                // Genuinely absent when the id does not belong to this user; the exists
                // rule has already reported that, so there is nothing more to check.
                $parent = $this->findParent();

                if (! $parent instanceof Category) {
                    return;
                }

                if ($category->isTopLevel()) {
                    $validator->errors()->add('parent_id', 'Only subcategories can be moved.');

                    return;
                }

                if (! $parent->isTopLevel()) {
                    $validator->errors()->add('parent_id', 'Categories can only be nested one level deep.');
                }

                // A subcategory inherits its parent's type, so moving across types would
                // silently reclassify it (CAT-02, CAT-05).
                if ($parent->type !== $category->type) {
                    $validator->errors()->add('parent_id', 'A subcategory can only move to a category of the same type.');
                }

                if ($parent->id === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');
                }
            },
        ];
    }

    /**
     * The validated parent. Only call once validation has passed.
     */
    public function newParent(): Category
    {
        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->whereKey($this->integer('parent_id'))
            ->firstOrFail();
    }

    private function findParent(): ?Category
    {
        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->whereKey($this->integer('parent_id'))
            ->first();
    }
}
