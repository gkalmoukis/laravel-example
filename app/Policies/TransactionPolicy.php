<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * See AccountPolicy: another user's record is reported as missing, never as forbidden, so
 * nothing about it leaks — not even that it exists (USR-02).
 */
final readonly class TransactionPolicy
{
    public function update(User $user, Transaction $transaction): Response
    {
        return $this->owns($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): Response
    {
        return $this->owns($user, $transaction);
    }

    private function owns(User $user, Transaction $transaction): Response
    {
        return $user->id === $transaction->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
