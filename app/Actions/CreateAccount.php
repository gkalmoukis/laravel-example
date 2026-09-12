<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Account;
use App\Models\User;

final readonly class CreateAccount
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Account
    {
        $highestSortOrder = $user->accounts()->max('sort_order');

        // Created through the relationship so the owner is set without mass assignment.
        return $user->accounts()->create([
            ...$attributes,
            'sort_order' => is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0,
        ]);
    }
}
