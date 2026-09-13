<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SalaryModel;
use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateSalaryModelRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'base_amount' => ['required', 'string'],
            'name' => ['sometimes', 'string', 'max:255'],

            'payments' => ['required', 'array'],
            'payments.*.enabled' => ['required', 'boolean'],
            'payments.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'payments.*.mode' => ['required', Rule::in([SalaryModel::MODE_MULTIPLIER, SalaryModel::MODE_FIXED_AMOUNT])],
            // A bonus is at most a year's salary; beyond that it is a typing mistake.
            'payments.*.multiplier' => ['required', 'numeric', 'min:0', 'max:12'],
            'payments.*.amount_cents' => ['required', 'integer', 'min:0'],
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
                    $this->baseAmount();
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('base_amount', 'Enter an amount like 1.800,00.');
                }
            },
        ];
    }

    public function baseAmount(): Money
    {
        return Money::fromInput($this->string('base_amount')->value());
    }

    public function salaryName(): string
    {
        $name = $this->string('name')->value();

        return $name === '' ? 'Salary' : $name;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function payments(): array
    {
        $payments = $this->input('payments');

        return is_array($payments) ? $payments : [];
    }
}
