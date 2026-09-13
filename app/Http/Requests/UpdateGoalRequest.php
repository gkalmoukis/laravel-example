<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * The type is absent on purpose: it is fixed at creation, because it decides where the
 * current amount comes from (GOAL-03).
 */
final class UpdateGoalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'string'],
            'current_amount' => ['nullable', 'string'],
            'monthly_contribution' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['target_amount', 'current_amount', 'monthly_contribution'] as $field) {
                    if ($this->moneyFor($field) === false) {
                        $validator->errors()->add($field, 'Enter an amount like 1.234,56.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function goalAttributes(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'target_amount_cents' => $this->money('target_amount'),
            'current_amount_cents' => $this->money('current_amount') ?? Money::zero(),
            'monthly_contribution_cents' => $this->money('monthly_contribution'),
            'target_date' => $this->string('target_date')->value() ?: null,
        ];
    }

    public function money(string $field): ?Money
    {
        $amount = $this->moneyFor($field);

        return $amount instanceof Money ? $amount : null;
    }

    private function moneyFor(string $field): Money|false|null
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
