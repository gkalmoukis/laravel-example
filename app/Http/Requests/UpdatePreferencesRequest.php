<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\UserPreference;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePreferencesRequest extends FormRequest
{
    /**
     * Currency and financial year start are absent on purpose: both are stored but fixed
     * in this version and shown read-only (PREF-02).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format_locale' => ['required', Rule::in(UserPreference::FORMAT_LOCALES)],

            // The IANA list from PHP itself, so no dependency is needed to validate it.
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],

            'salary_payments' => ['required', 'integer', Rule::in(UserPreference::SALARY_PAYMENTS)],

            'default_account_id' => [
                'nullable',
                'integer',
                // Scoped to the owner and to active accounts, so another user's account
                // can never be referenced (USR-03, ACC-01).
                Rule::exists('accounts', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('is_active', true),
            ],

            'emergency_fund_months' => ['required', 'integer', 'min:1', 'max:24'],

            'budget_warning_threshold_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_account_id.exists' => 'Choose one of your active accounts.',
            'emergency_fund_months.max' => 'Coverage can be at most 24 months.',
        ];
    }
}
