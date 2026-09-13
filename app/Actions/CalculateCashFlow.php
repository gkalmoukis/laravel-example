<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\BalanceLine;
use App\Data\CashFlow;
use App\Data\CashFlowMonth;
use App\Data\MonthlyFigures;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * What the balance does over the year, under the plan, in reality, and in the forecast
 * (§7.2).
 *
 * All three series are running totals from the same opening position, so each month's
 * closing balance is the next month's opening one. `today` is a parameter rather than a
 * call to now(), so a report can be asked what it looked like on any given day and tests
 * can pin the date (§10.2).
 */
final readonly class CalculateCashFlow
{
    private const int MONTHS = 12;

    public function __construct(
        private CalculateMonthlyFigures $figures,
        private UpdateOpeningPosition $openingPosition,
    ) {}

    public function handle(FinancialYear $financialYear, CarbonImmutable $today): CashFlow
    {
        $figures = $this->figures->handle($financialYear);
        $opening = $this->openingPosition->openingLiquidBalance($financialYear)->cents;

        $lastActualMonth = $this->lastActualMonth($figures, $financialYear, $today);

        $months = [];

        $plannedBalance = $opening;
        $forecastBalance = $opening;
        $actualBalance = $opening;

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $totals = $figures->month($month);

            $planned = $this->line(
                $plannedBalance,
                $totals->plannedIncomeCents,
                $totals->plannedExpenseCents,
            );

            $forecast = $this->line(
                $forecastBalance,
                $totals->forecastIncomeCents,
                $totals->forecastExpenseCents,
            );

            $plannedBalance = $planned->closingCents;
            $forecastBalance = $forecast->closingCents;

            // Beyond the last month the user has reached, there is no actual to report.
            $actual = null;

            if ($lastActualMonth !== null && $month <= $lastActualMonth) {
                $actual = $this->line(
                    $actualBalance,
                    $totals->actualIncomeCents,
                    $totals->actualExpenseCents,
                );

                $actualBalance = $actual->closingCents;
            }

            $months[$month] = new CashFlowMonth(
                month: $month,
                status: $totals->status,
                planned: $planned,
                forecast: $forecast,
                actual: $actual,
            );
        }

        return new CashFlow(
            openingBalanceCents: $opening,
            months: $months,
            lastActualMonth: $lastActualMonth,
            currentAvailableCents: $this->currentAvailable($financialYear, $today, $opening),
        );
    }

    private function line(int $opening, int $income, int $expense): BalanceLine
    {
        $net = $income - $expense;

        return new BalanceLine(
            openingCents: $opening,
            incomeCents: $income,
            expenseCents: $expense,
            netCents: $net,
            closingCents: $opening + $net,
        );
    }

    /**
     * How far the actual series runs: the latest month with anything recorded in it, or
     * the current month when the year is the one being lived through, whichever is later.
     *
     * A past year with nothing recorded, or a year still in the future, has no actual
     * series at all rather than a flat line at the opening balance.
     */
    private function lastActualMonth(MonthlyFigures $figures, FinancialYear $financialYear, CarbonImmutable $today): ?int
    {
        $latestTouched = null;

        for ($month = 1; $month <= self::MONTHS; $month++) {
            if ($figures->month($month)->status !== MonthStatus::NotStarted) {
                $latestTouched = $month;
            }
        }

        if ($today->year !== $financialYear->year) {
            return $latestTouched;
        }

        return max($latestTouched ?? 0, $today->month);
    }

    /**
     * What the user can actually spend right now: the opening position plus everything
     * that has genuinely happened on or before today (§7.2).
     */
    private function currentAvailable(FinancialYear $financialYear, CarbonImmutable $today, int $opening): int
    {
        $rows = Transaction::valid()
            ->where('transactions.user_id', $financialYear->user_id)
            ->whereYear('occurred_on', $financialYear->year)
            ->whereDate('occurred_on', '<=', $today->toDateString())
            ->groupBy('type')
            ->get(['type', Transaction::query()->raw('SUM(amount_cents) as total_cents')]);

        $balance = $opening;

        foreach ($rows as $row) {
            $amount = (int) (is_numeric($row->getAttribute('total_cents')) ? $row->getAttribute('total_cents') : 0);

            $balance += $row->type === TransactionType::Income ? $amount : -$amount;
        }

        return $balance;
    }
}
