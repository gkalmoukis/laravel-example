<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * The month-end value of each holding (MON-06).
 *
 * Amounts are typed the way the user says them, so they are parsed rather than matched
 * against a pattern (TXQ-03).
 */
final class UpdateNetWorthSnapshotRequest extends FormRequest
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
                foreach ($this->holdingRows() as $index => $row) {
                    try {
                        Money::fromInput($row['amount']);
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
     * @return array<int, Money> amount for each holding, keyed by item id
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->holdingRows() as $row) {
            $values[(int) $row['id']] = Money::fromInput($row['amount']);
        }

        return $values;
    }

    /**
     * @return list<array{id: string, amount: string}>
     */
    private function holdingRows(): array
    {
        $rows = [];

        foreach ($this->collect('holdings') as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['amount'])) {
                continue;
            }

            $rows[] = [
                'id' => (string) (is_scalar($row['id']) ? $row['id'] : ''),
                'amount' => (string) (is_scalar($row['amount']) ? $row['amount'] : ''),
            ];
        }

        return $rows;
    }
}
