<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateEmergencyFundRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // One to twenty-four months of cover. Less than a month is not a fund, and
            // more than two years is a savings goal by another name (6.3).
            'months_of_cover' => ['required', 'integer', 'min:1', 'max:24'],

            'essential_category_ids' => ['present', 'array'],
            'essential_category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],

            'custom_target' => ['nullable', 'string'],
            'monthly_contribution' => ['nullable', 'string'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['custom_target', 'monthly_contribution'] as $field) {
                    if ($this->amountFor($field) === false) {
                        $validator->errors()->add($field, 'Enter an amount like 1.234,56.');
                    }
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function essentialCategoryIds(): array
    {
        $ids = [];

        foreach ($this->collect('essential_category_ids') as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    public function customTarget(): ?Money
    {
        $amount = $this->amountFor('custom_target');

        return $amount instanceof Money ? $amount : null;
    }

    public function monthlyContribution(): ?Money
    {
        $amount = $this->amountFor('monthly_contribution');

        return $amount instanceof Money ? $amount : null;
    }

    /**
     * The parsed amount, null when the field was left empty, or false when what was typed
     * is not an amount at all.
     */
    private function amountFor(string $field): Money|false|null
    {
        $value = mb_trim($this->string($field)->value());

        if ($value === '') {
            return null;
        }

        try {
            return Money::fromInput($value);
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
