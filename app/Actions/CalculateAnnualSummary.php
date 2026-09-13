<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\AnnualSummary;
use App\Data\BalanceLine;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * The year's totals, and how far it has drifted from the plan it started with (§7.3).
 *
 * Drift is measured against the baseline captured when setup finished, because the point
 * is to compare against what was originally intended rather than against a plan quietly
 * edited to match reality. Without a baseline there is still something useful to say, so
 * the current plan is used instead and the caller is told which it was (FC-06).
 */
final readonly class CalculateAnnualSummary
{
    private const int MONTHS = 12;

    public function __construct(private CalculateCashFlow $cashFlow) {}

    public function handle(FinancialYear $financialYear, CarbonImmutable $today): AnnualSummary
    {
        $cashFlow = $this->cashFlow->handle($financialYear, $today);

        $plannedIncome = 0;
        $plannedExpense = 0;
        $forecastIncome = 0;
        $forecastExpense = 0;
        $completedIncome = 0;
        $completedExpense = 0;
        $completedPlannedIncome = 0;
        $completedPlannedExpense = 0;
        $completedMonths = 0;

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $line = $cashFlow->month($month);

            $plannedIncome += $line->planned->incomeCents;
            $plannedExpense += $line->planned->expenseCents;
            $forecastIncome += $line->forecast->incomeCents;
            $forecastExpense += $line->forecast->expenseCents;

            if ($line->status !== MonthStatus::Complete) {
                continue;
            }

            $completedMonths++;
            $completedPlannedIncome += $line->planned->incomeCents;
            $completedPlannedExpense += $line->planned->expenseCents;

            if ($line->actual instanceof BalanceLine) {
                $completedIncome += $line->actual->incomeCents;
                $completedExpense += $line->actual->expenseCents;
            }
        }

        $soFar = $this->actualSoFar($financialYear, $today);
        $forecastYearEnd = $cashFlow->forecastYearEndCents();
        $plannedYearEnd = $cashFlow->plannedYearEndCents();
        $baselineYearEnd = $this->baselineYearEnd($financialYear);

        return new AnnualSummary(
            plannedIncomeCents: $plannedIncome,
            plannedExpenseCents: $plannedExpense,
            actualSoFarIncomeCents: $soFar[TransactionType::Income->value],
            actualSoFarExpenseCents: $soFar[TransactionType::Expense->value],
            completedIncomeCents: $completedIncome,
            completedExpenseCents: $completedExpense,
            completedPlannedIncomeCents: $completedPlannedIncome,
            completedPlannedExpenseCents: $completedPlannedExpense,
            completedMonths: $completedMonths,
            forecastIncomeCents: $forecastIncome,
            forecastExpenseCents: $forecastExpense,
            plannedYearEndCents: $plannedYearEnd,
            forecastYearEndCents: $forecastYearEnd,
            deviationCents: $forecastYearEnd - ($baselineYearEnd ?? $plannedYearEnd),
            hasBaseline: $baselineYearEnd !== null,
        );
    }

    /**
     * Everything recorded in the year up to today, whatever state its month is in — the
     * live figure the dashboard labels "Actual so far".
     *
     * @return array<string, int>
     */
    private function actualSoFar(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $totals = [
            TransactionType::Income->value => 0,
            TransactionType::Expense->value => 0,
        ];

        $rows = Transaction::valid()
            ->where('transactions.user_id', $financialYear->user_id)
            ->whereYear('occurred_on', $financialYear->year)
            ->whereDate('occurred_on', '<=', $today->toDateString())
            ->groupBy('type')
            ->get(['type', Transaction::query()->raw('SUM(amount_cents) as total_cents')]);

        foreach ($rows as $row) {
            $total = $row->getAttribute('total_cents');

            $totals[$row->type->value] = (int) (is_numeric($total) ? $total : 0);
        }

        return $totals;
    }

    private function baselineYearEnd(FinancialYear $financialYear): ?int
    {
        $baseline = $financialYear->baseline;

        if ($baseline === null) {
            return null;
        }

        $yearEnd = $baseline['year_end_balance_cents'] ?? null;

        return is_numeric($yearEnd) ? (int) $yearEnd : null;
    }
}
