<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GoalType;
use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class StoreGoalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The emergency fund is provisioned, never created: one per account (GOAL-01).
            'type' => ['required', new Enum(GoalType::class), Rule::notIn([GoalType::EmergencyFund->value])],
            'target_amount' => ['required', 'string'],
            'current_amount' => ['nullable', 'string'],
            'monthly_contribution' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
            'financial_year_id' => [
                'nullable',
                'integer',
                Rule::exists('financial_years', 'id')->where('user_id', $this->user()?->id),
            ],
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

            function (Validator $validator): void {
                // A balance at the end of which year? Without one there is nothing to
                // measure it against (6.3).
                if ($this->string('type')->value() === GoalType::YearEndBalance->value
                    && $this->integer('financial_year_id') === 0) {
                    $validator->errors()->add('financial_year_id', 'Choose which year this balance is for.');
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
            'type' => $this->string('type')->value(),
            'target_amount_cents' => $this->money('target_amount'),
            'current_amount_cents' => $this->money('current_amount') ?? Money::zero(),
            'monthly_contribution_cents' => $this->money('monthly_contribution'),
            'target_date' => $this->string('target_date')->value() ?: null,
            'financial_year_id' => $this->integer('financial_year_id') ?: null,
        ];
    }

    public function money(string $field): ?Money
    {
        $amount = $this->moneyFor($field);

        return $amount instanceof Money ? $amount : null;
    }

    /**
     * The parsed amount, null when left empty, false when what was typed is not one.
     */
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
