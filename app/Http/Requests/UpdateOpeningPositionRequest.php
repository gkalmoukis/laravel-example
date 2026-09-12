<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateOpeningPositionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'holdings' => ['required', 'array'],
            'holdings.*.id' => ['required', 'integer'],
            'holdings.*.amount' => ['required', 'string'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->rawHoldings() as $index => $holding) {
                    $amount = $holding['amount'] ?? null;

                    try {
                        Money::fromInput(is_string($amount) ? $amount : '');
                    } catch (InvalidArgumentException) {
                        $validator->errors()->add(
                            sprintf('holdings.%d.amount', $index),
                            'Enter an amount like 1.234,56.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * The amounts, parsed into money and keyed by holding.
     *
     * @return array<int, Money>
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->rawHoldings() as $holding) {
            $id = $holding['id'] ?? null;

            $amount = $holding['amount'] ?? null;

            if (is_numeric($id) && is_string($amount)) {
                $values[(int) $id] = Money::fromInput($amount);
            }
        }

        return $values;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rawHoldings(): array
    {
        $holdings = $this->input('holdings');

        if (! is_array($holdings)) {
            return [];
        }

        return array_values(array_filter($holdings, is_array(...)));
    }
}
