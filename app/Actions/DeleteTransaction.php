<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Removes a transaction for good (TXF-03).
 *
 * There is no archive: a transaction the user says never happened should stop affecting
 * every figure derived from it, and keeping a hidden copy would only make the numbers
 * harder to explain.
 */
final readonly class DeleteTransaction
{
    public function __construct(private AllowChangeInMonth $allowChange) {}

    public function handle(Transaction $transaction, bool $reopenMonth = false): void
    {
        DB::transaction(function () use ($transaction, $reopenMonth): void {
            $this->allowChange->handle($transaction->user, $transaction->occurred_on, $reopenMonth);

            $transaction->delete();
        });
    }
}
