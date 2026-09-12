<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCategoryRequest extends FormRequest
{
    /**
     * The type is absent on purpose: it is fixed at creation (CAT-03). System categories
     * can be renamed like any other, they simply cannot be removed (CAT-07).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_essential' => ['sometimes', 'boolean'],
            'is_irregular' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
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

                $exists = Category::query()
                    ->where('user_id', $category->user_id)
                    ->where('name', $this->string('name')->value())
                    ->when(
                        $category->parent_id === null,
                        fn (Builder $query): Builder => $query->whereNull('parent_id'),
                        fn (Builder $query): Builder => $query->where('parent_id', $category->parent_id),
                    )
                    ->whereKeyNot($category->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'You already have a category with that name here.');
                }
            },
        ];
    }
}
