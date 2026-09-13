<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NetWorthItemKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreNetWorthItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', new Enum(NetWorthItemKind::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemAttributes(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'kind' => $this->string('kind')->value(),
        ];
    }
}
