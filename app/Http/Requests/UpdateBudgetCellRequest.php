<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateBudgetCellRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id)->whereNull('parent_id'),
            ],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'string'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                try {
                    $this->amount();
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('amount', 'Enter an amount like 1.234,56.');
                }
            },
        ];
    }

    public function amount(): Money
    {
        return Money::fromInput($this->string('amount')->value());
    }
}
