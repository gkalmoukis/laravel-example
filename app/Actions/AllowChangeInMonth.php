<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Lets a change through only while the month it falls in is still open (TXV-02).
 *
 * A finished month is the user's statement that they are done with it, so editing inside
 * one is refused rather than quietly accepted — otherwise a figure they already signed off
 * would change underneath them. Refusing outright would be a dead end, so the caller may
 * ask to reopen, and the reopening and the change then happen in one transaction.
 *
 * The refusal marks `reopen_month` as well as the date: the date message explains what
 * happened, and the flag on the field that would lift the refusal is what lets the form
 * offer to reopen. It rides on a real field on purpose — a key the form does not know
 * about never reaches its typed errors.
 */
final readonly class AllowChangeInMonth
{
    public function __construct(private ReopenMonth $reopenMonth) {}

    public function handle(User $user, CarbonInterface $date, bool $reopen): void
    {
        $financialYear = $user->financialYears()->where('year', $date->year)->first();

        // A year with no plan has no months to finish, so there is nothing to guard. This
        // is the ordinary case for a transaction recorded ahead of its year (TXQ-08).
        if (! $financialYear instanceof FinancialYear) {
            return;
        }

        if (! MonthClosure::isMonthComplete($user->id, $date->year, $date->month)) {
            return;
        }

        if (! $reopen) {
            throw ValidationException::withMessages([
                'occurred_on' => sprintf(
                    '%s %d is marked complete. Reopen it to save this change.',
                    $date->format('F'),
                    $date->year,
                ),
                'reopen_month' => (string) $date->month,
            ]);
        }

        $this->reopenMonth->handle($financialYear, $date->month);
    }
}
