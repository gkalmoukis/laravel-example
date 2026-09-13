<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a month as finished (MON-03 step 5, MON-04).
 *
 * Completion is the user's statement that a month is final, and the forecast treats it as
 * such — a complete month stops taking its plan into account. Two things make that
 * statement unsafe, so both are refused rather than warned about:
 *
 * A month with flagged transactions cannot be final, because the numbers it would freeze
 * are ones the user has not finished correcting. A month that has not started yet cannot
 * be final either — there is nothing to be done with it.
 *
 * Completing the current month early is a different matter: the user may genuinely be
 * done, so it is allowed once they have confirmed they mean it.
 */
final readonly class CompleteMonth
{
    public function handle(FinancialYear $financialYear, int $month, CarbonImmutable $today, bool $confirmed = false): MonthClosure
    {
        $this->guardFlaggedTransactions($financialYear, $month);
        $this->guardFutureMonth($financialYear, $month, $today, $confirmed);

        return DB::transaction(fn (): MonthClosure => MonthClosure::query()->updateOrCreate(
            ['financial_year_id' => $financialYear->id, 'month' => $month],
            ['completed_at' => now()],
        ));
    }

    /**
     * Whether finishing this month needs the user to confirm first (MON-04).
     *
     * The month they are living through is the only one that can be finished early, and
     * doing so on the 3rd is a different decision from doing so on the 31st.
     */
    public function needsConfirmation(FinancialYear $financialYear, int $month, CarbonImmutable $today): bool
    {
        if ($financialYear->year !== $today->year || $month !== $today->month) {
            return false;
        }

        return ! $today->isSameDay($today->endOfMonth());
    }

    private function guardFlaggedTransactions(FinancialYear $financialYear, int $month): void
    {
        $flagged = Transaction::flagged()
            ->where('transactions.user_id', $financialYear->user_id)
            ->whereYear('occurred_on', $financialYear->year)
            ->whereMonth('occurred_on', $month)
            ->count();

        if ($flagged === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'month' => sprintf(
                'Fix %d %s first.',
                $flagged,
                $flagged === 1 ? 'transaction' : 'transactions',
            ),
        ]);
    }

    private function guardFutureMonth(FinancialYear $financialYear, int $month, CarbonImmutable $today, bool $confirmed): void
    {
        // Built from the date the year and month name rather than parsed, so there is no
        // nullable "did that string make sense" to reason about.
        $startsOn = $today->setDate($financialYear->year, $month, 1)->startOfDay();

        if ($startsOn->greaterThan($today)) {
            throw ValidationException::withMessages([
                'month' => 'This month has not started yet.',
            ]);
        }

        if ($this->needsConfirmation($financialYear, $month, $today) && ! $confirmed) {
            throw ValidationException::withMessages([
                'confirmed' => 'This month is not over yet. Confirm you have recorded everything.',
            ]);
        }
    }
}
