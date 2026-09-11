<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class StoreInvitationAcceptanceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // The email is taken from the invitation, never from the request, so the
            // address that was invited is the address that gets the account (INV-06).
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
