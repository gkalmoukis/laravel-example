<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class StorePlanItemRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const array PLAN_ITEM_FIELDS = [
        'name',
        'type',
        'kind',
        'frequency',
        'allocation',
        'start_month',
        'payment_day',
        'is_fixed',
        'notes',
        'category_id',
        'subcategory_id',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(TransactionType::class)],
            'kind' => ['required', new Enum(PlanItemKind::class)],
            'frequency' => ['required', new Enum(Frequency::class)],
            'allocation' => ['sometimes', new Enum(Allocation::class)],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            // Resolves to the last day of a shorter month, so the 31st is allowed
            // (EDGE-05).
            'payment_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'is_fixed' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'string'],

            'category_id' => [
                'required',
                'integer',
                // Scoped to the owner, so another user's category cannot be planned
                // against (USR-03).
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id)->whereNull('parent_id'),
            ],

            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],

            'custom_months' => ['sometimes', 'array'],
            'custom_months.*' => ['integer', 'min:1', 'max:12'],
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

    /**
     * @return list<int>
     */
    public function customMonths(): array
    {
        $months = $this->input('custom_months');

        if (! is_array($months)) {
            return [];
        }

        return array_values(array_map(intval(...), array_filter($months, is_numeric(...))));
    }

    /**
     * Built key by key rather than by excluding, so the shape is known rather than
     * whatever happened to be posted.
     *
     * @return array<string, mixed>
     */
    public function planItemAttributes(): array
    {
        $attributes = [];

        foreach (self::PLAN_ITEM_FIELDS as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->input($field);
            }
        }

        return $attributes;
    }
}
