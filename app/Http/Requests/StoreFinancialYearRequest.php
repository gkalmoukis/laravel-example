<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\FinancialYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFinancialYearRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'min:'.FinancialYear::EARLIEST_YEAR,
                'max:'.FinancialYear::latestSelectableYear(),
                // One plan per year per person (YEAR-01).
                Rule::unique('financial_years', 'year')->where('user_id', $this->user()?->id),
            ],

            // Offered only when an earlier year exists (YEAR-02).
            'copy_from_id' => [
                'nullable',
                'integer',
                Rule::exists('financial_years', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.unique' => 'You already have a plan for that year.',
        ];
    }
}
