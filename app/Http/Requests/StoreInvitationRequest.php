<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInvitationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],

            'is_admin' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'That email address already has an account.',
        ];
    }

    /**
     * The address is normalised before validation so that casing or stray whitespace
     * cannot slip an invitation past the "already a user" check (INV-04).
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(mb_trim($this->string('email')->value())),
        ]);
    }
}
