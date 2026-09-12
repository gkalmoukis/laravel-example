<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Account;

final readonly class UpdateAccount
{
    /**
     * Also the way an account is reactivated, since `is_active` is an ordinary field
     * (ACC-01).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Account $account, array $attributes): Account
    {
        $account->update($attributes);

        return $account;
    }
}
