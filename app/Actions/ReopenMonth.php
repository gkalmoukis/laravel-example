<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\MonthClosure;

/**
 * Unfinishes a month, so its figures go back to being provisional (MON-05).
 *
 * The closure row is kept rather than deleted: it is the record that the month was once
 * finished, and month status is derived from `completed_at` alone (MON-01).
 */
final readonly class ReopenMonth
{
    public function handle(FinancialYear $financialYear, int $month): void
    {
        MonthClosure::query()
            ->where('financial_year_id', $financialYear->id)
            ->where('month', $month)
            ->update(['completed_at' => null]);
    }
}
