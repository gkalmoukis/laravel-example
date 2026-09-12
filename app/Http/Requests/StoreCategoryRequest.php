<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class StoreCategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Required only for a top-level category; a subcategory inherits its parent's
            // type, so supplying one would be meaningless (CAT-02).
            'type' => ['required_without:parent_id', new Enum(TransactionType::class)],

            'parent_id' => [
                'nullable',
                'integer',
                // Scoped to the owner, so another user's category can never be used as a
                // parent (USR-03).
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],

            'is_essential' => ['sometimes', 'boolean'],
            'is_irregular' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parent = $this->parentCategory();

                // Only one level of nesting is allowed (CAT-02).
                if ($parent instanceof Category && ! $parent->isTopLevel()) {
                    $validator->errors()->add('parent_id', 'Categories can only be nested one level deep.');
                }

                $this->validateNameIsUnique($validator, $parent);
            },
        ];
    }

    public function parentCategory(): ?Category
    {
        $parentId = $this->integer('parent_id');

        if ($parentId === 0) {
            return null;
        }

        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->whereKey($parentId)
            ->first();
    }

    /**
     * MySQL treats NULLs as distinct in unique indexes, so the index over
     * (user_id, parent_id, name) does not stop two top-level categories sharing a name.
     * That case is enforced here.
     */
    private function validateNameIsUnique(Validator $validator, ?Category $parent): void
    {
        $exists = Category::query()
            ->where('user_id', $this->user()?->id)
            ->where('name', $this->string('name')->value())
            ->when(
                $parent instanceof Category,
                fn (Builder $query): Builder => $query->where('parent_id', $parent?->id),
                fn (Builder $query): Builder => $query->whereNull('parent_id'),
            )
            ->exists();

        if ($exists) {
            $validator->errors()->add('name', 'You already have a category with that name here.');
        }
    }
}
