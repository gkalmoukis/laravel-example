<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMonthCompletionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Whether the user has said they mean to finish a month that is still running
     * (MON-04).
     */
    public function isConfirmed(): bool
    {
        return $this->boolean('confirmed');
    }
}
