<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EntrySource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Everything that would make a transaction meaningless is refused here (TXV-01). What is
 * merely questionable — a date in a year with no plan, a subcategory since moved away — is
 * allowed through and flagged on read instead, so the user is never stopped mid-entry by
 * something they can put right later (TXV-03).
 */
final class StoreTransactionRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const array TRANSACTION_FIELDS = [
        'type',
        'category_id',
        'subcategory_id',
        'account_id',
        'description',
        'notes',
        'entry_source',
        'entry_duration_ms',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'type' => ['required', new Enum(TransactionType::class)],
            'amount' => ['required', 'string'],
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'min:1', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Only an active, top-level category of the user's own (TXV-01, USR-03).
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('parent_id')
                    ->where('is_active', true),
            ],

            // A subcategory has to sit under the category actually chosen, or the
            // transaction would be filed somewhere the reports never look.
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->where('parent_id', $this->integer('category_id'))
                    ->where('is_active', true),
            ],

            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->where('is_active', true),
            ],

            'entry_source' => ['sometimes', new Enum(EntrySource::class)],
            'entry_duration_ms' => ['nullable', 'integer', 'min:0'],
            'reopen_month' => ['sometimes', 'boolean'],
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
                    $amount = $this->amount();
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('amount', $this->amountProblem());

                    return;
                }

                if ($amount->isZero()) {
                    $validator->errors()->add('amount', 'Enter an amount greater than zero.');
                }
            },

            function (Validator $validator): void {
                $category = $this->chosenCategory();

                // Money filed under a category that records the opposite direction would
                // be counted the wrong way round for the rest of the year.
                if ($category instanceof Category && $category->type->value !== $this->string('type')->value()) {
                    $validator->errors()->add(
                        'category_id',
                        sprintf('Choose a category for %s.', $this->string('type')->value()),
                    );
                }
            },
        ];
    }

    public function amount(): Money
    {
        return Money::fromInput($this->string('amount')->value());
    }

    /**
     * The day it happened. A calendar date, not a moment: which month a transaction
     * counts in must not depend on the hour it was typed (§6.1).
     */
    public function occurredOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('occurred_on')->value())->startOfDay();
    }

    /**
     * Whether the user has asked to reopen a finished month so this change can go
     * through (TXV-02).
     */
    public function reopensMonth(): bool
    {
        return $this->boolean('reopen_month');
    }

    /**
     * Built key by key rather than by excluding, so the shape is known rather than
     * whatever happened to be posted.
     *
     * @return array<string, mixed>
     */
    public function transactionAttributes(): array
    {
        $attributes = [];

        foreach (self::TRANSACTION_FIELDS as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->input($field);
            }
        }

        $attributes['entry_source'] ??= EntrySource::Form->value;

        return $attributes;
    }

    /**
     * Tells "not a number" apart from "far too large", because the advice differs.
     */
    private function amountProblem(): string
    {
        $digits = preg_replace('/\D/', '', $this->string('amount')->value()) ?? '';

        return mb_strlen($digits) > 11
            ? 'That amount is too large.'
            : 'Enter an amount like 1.234,56.';
    }

    private function chosenCategory(): ?Category
    {
        $categoryId = $this->integer('category_id');

        if ($categoryId === 0) {
            return null;
        }

        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->whereKey($categoryId)
            ->first();
    }
}
