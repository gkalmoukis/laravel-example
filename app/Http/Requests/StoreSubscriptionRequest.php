<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Subscription;
use App\ValueObjects\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * A new subscription. Its subcategory is created from the name, so the charge has
 * somewhere to be filed from the moment it exists (SUB-02).
 */
final class StoreSubscriptionRequest extends FormRequest
{
    /**
     * Only the four whole-month frequencies are allowed. "Once" is not a subscription,
     * and "Custom" has no interval for a billing sequence to be derived from (§6.3).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'frequency' => ['required', Rule::in(array_keys(Subscription::intervals()))],
            'billing_anchor_date' => ['required', 'date'],

            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('parent_id')
                    ->where('type', 'expense'),
            ],

            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],

            'notes' => ['nullable', 'string', 'max:2000'],
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
                    $validator->errors()->add('amount', 'Enter an amount like 1.234,56.');

                    return;
                }

                if ($amount->isZero()) {
                    $validator->errors()->add('amount', 'Enter an amount greater than zero.');
                }
            },
        ];
    }

    /**
     * Built key by key rather than by excluding, so the shape is known rather than
     * whatever happened to be posted.
     *
     * @return array<string, mixed>
     */
    public function subscriptionAttributes(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'amount_cents' => $this->amount(),
            'frequency' => $this->string('frequency')->value(),
            'billing_anchor_date' => $this->string('billing_anchor_date')->value(),
            'category_id' => $this->integer('category_id'),
            'account_id' => $this->integer('account_id') ?: null,
            'notes' => $this->string('notes')->value() ?: null,
        ];
    }

    public function amount(): Money
    {
        return Money::fromInput($this->string('amount')->value());
    }
}
