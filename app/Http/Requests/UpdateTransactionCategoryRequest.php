<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The destination for a bulk refile (TXL-04). Which rows may actually move is decided by
 * the Action, row by row; what is refused here is a destination that could never be right
 * for any of them.
 */
final class UpdateTransactionCategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer'],

            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('parent_id')
                    ->where('is_active', true),
            ],

            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->where('parent_id', $this->integer('category_id'))
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function transactionIds(): array
    {
        $ids = [];

        foreach ($this->collect('transaction_ids') as $id) {
            $ids[] = (int) (is_numeric($id) ? $id : 0);
        }

        return $ids;
    }

    public function category(): Category
    {
        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->whereKey($this->integer('category_id'))
            ->firstOrFail();
    }

    public function subcategory(): ?Category
    {
        $subcategoryId = $this->integer('subcategory_id');

        if ($subcategoryId === 0) {
            return null;
        }

        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->whereKey($subcategoryId)
            ->first();
    }
}
