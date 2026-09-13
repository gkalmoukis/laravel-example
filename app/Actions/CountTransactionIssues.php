<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\TransactionIssueCount;
use App\Enums\TransactionIssue;
use App\Models\FinancialYear;
use App\Models\Transaction;

/**
 * The flagged transactions a year is answerable for (ALRT-01, ALRT-02, DASH-03).
 *
 * Nothing is stored: the flags are derived columns, so a transaction stops being counted
 * the moment its cause is fixed (TXV-04).
 */
final readonly class CountTransactionIssues
{
    public function handle(FinancialYear $financialYear): TransactionIssueCount
    {
        $flagged = Transaction::withIssues(
            Transaction::flagged($financialYear->user->transactions()->getQuery())
        )->get();

        $total = 0;
        $mismatched = 0;

        foreach ($flagged as $transaction) {
            $issues = $transaction->loadedIssues();

            // A transaction with no year at all belongs to no year's list, so it is
            // reported whichever year is selected — otherwise nobody would ever see it.
            $belongs = $transaction->occurred_on->year === $financialYear->year
                || in_array(TransactionIssue::NoFinancialYear, $issues, true);

            if (! $belongs) {
                continue;
            }

            $total++;

            if (in_array(TransactionIssue::CategoryTypeMismatch, $issues, true)) {
                $mismatched++;
            }
        }

        return new TransactionIssueCount($total, $mismatched);
    }
}
