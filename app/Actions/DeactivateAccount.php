<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Account;

final readonly class DeactivateAccount
{
    /**
     * Accounts are deactivated rather than deleted, so transactions that already point at
     * one keep showing where the money went. Deactivated accounts disappear from pickers
     * (ACC-01).
     */
    public function handle(Account $account): void
    {
        $account->update(['is_active' => false]);
    }
}
