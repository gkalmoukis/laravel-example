<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Alert;
use App\Data\CashFlowMonth;
use App\Data\MonthlyFigures;
use App\Enums\AlertType;
use App\Enums\MonthStatus;
use App\Enums\VarianceStatus;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;

/**
 * What the year is trying to tell the user (§8.19).
 *
 * Every alert is worked out here from the same Actions the screens use, so an alert can
 * never disagree with the page it links to, and fixing the cause removes the alert on the
 * next request. Nothing is stored, and nothing is emailed or pushed.
 */
final readonly class BuildAlerts
{
    private const int MONTHS = 12;

    /**
     * Written out rather than formatted from a date, so a month name never depends on a
     * date being constructible.
     *
     * @var list<string>
     */
    private const array MONTH_NAMES = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    public function __construct(
        private CalculateMonthlyFigures $figures,
        private CalculateCashFlow $cashFlow,
        private CalculateVariances $variances,
        private CalculateEmergencyFund $emergencyFund,
        private CalculateGoalProgress $goals,
        private CountTransactionIssues $issues,
    ) {}

    /**
     * @return list<Alert>
     */
    public function handle(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $flow = $this->cashFlow->handle($financialYear, $today);
        $monthly = $this->figures->handle($financialYear);

        $alerts = [
            $this->negativeBalance($financialYear, $flow->months),
            $this->belowEmergencyFund($financialYear, $flow->months, $today),
            ...$this->transactionIssues($financialYear),
            $this->budgetOverrun($financialYear, $monthly, $today),
            $this->incompleteMonth($financialYear, $monthly, $today),
            $this->goalOffTrack($financialYear, $today),
        ];

        $alerts = array_values(array_filter($alerts, fn (?Alert $alert): bool => $alert instanceof Alert));

        usort($alerts, fn (Alert $a, Alert $b): int => $a->severity() <=> $b->severity());

        return $alerts;
    }

    /**
     * ALRT-07: the forecast goes below zero in some month.
     *
     * @param  array<int, CashFlowMonth>  $months
     */
    private function negativeBalance(FinancialYear $financialYear, array $months): ?Alert
    {
        foreach ($months as $month) {
            if ($month->forecast->closingCents < 0) {
                return new Alert(
                    AlertType::NegativeForecastBalance,
                    $this->monthName($month->month),
                    route('forecast.index', ['year' => $financialYear->year]),
                );
            }
        }

        return null;
    }

    /**
     * ALRT-06: the lowest forecast balance still to come is below what is set aside.
     *
     * Only months the user has not lived through yet: a dip that already happened is
     * history, and there is nothing left to do about it. A year already over therefore
     * raises nothing, and a year not yet begun is measured across all twelve months.
     *
     * @param  array<int, CashFlowMonth>  $months
     */
    private function belowEmergencyFund(FinancialYear $financialYear, array $months, CarbonImmutable $today): ?Alert
    {
        $from = $this->firstMonthStillToCome($financialYear, $today);

        if ($from === null) {
            return null;
        }

        $reserve = $this->emergencyFund->handle($financialYear, $today)->currentCents;

        $lowest = null;

        for ($month = $from; $month <= self::MONTHS; $month++) {
            $closing = $months[$month]->forecast->closingCents;

            if ($lowest === null || $closing < $months[$lowest]->forecast->closingCents) {
                $lowest = $month;
            }
        }

        if ($lowest === null || $months[$lowest]->forecast->closingCents >= $reserve) {
            return null;
        }

        return new Alert(
            AlertType::ForecastBelowEmergencyFund,
            $this->monthName($lowest),
            route('forecast.index', ['year' => $financialYear->year]),
        );
    }

    /**
     * ALRT-01 and ALRT-02: transactions that cannot be counted as they stand.
     *
     * The type mismatch gets its own entry because its fix is different — one needs a
     * plan for the year, the other needs the transaction refiled (TXV-05).
     *
     * @return list<Alert>
     */
    private function transactionIssues(FinancialYear $financialYear): array
    {
        $count = $this->issues->handle($financialYear);

        if (! $count->any()) {
            return [];
        }

        $url = route('transactions.index', ['year' => $financialYear->year, 'issues' => 1]);

        $alerts = [new Alert(AlertType::TransactionsWithIssues, $this->count($count->total), $url)];

        if ($count->categoryTypeMismatch > 0) {
            $alerts[] = new Alert(AlertType::CategoryTypeMismatch, $this->count($count->categoryTypeMismatch), $url);
        }

        return $alerts;
    }

    /**
     * ALRT-03: an expense category is over budget in the month that matters.
     *
     * That is the month being lived through, and the last one signed off — the two a
     * person would actually look at.
     */
    private function budgetOverrun(FinancialYear $financialYear, MonthlyFigures $monthly, CarbonImmutable $today): ?Alert
    {
        foreach ($this->monthsWorthChecking($financialYear, $monthly, $today) as $month) {
            $over = $this->firstOverspentCategory($financialYear, $month);

            if ($over !== null) {
                return new Alert(
                    AlertType::BudgetOverrun,
                    $over,
                    route('comparison.index', ['year' => $financialYear->year, 'month' => $month]),
                );
            }
        }

        return null;
    }

    private function firstOverspentCategory(FinancialYear $financialYear, int $month): ?string
    {
        foreach ($this->variances->handle($financialYear, $month)->expenses as $variance) {
            if ($variance->status === VarianceStatus::Over) {
                return $variance->categoryName;
            }
        }

        return null;
    }

    /**
     * The current month, and the latest one already signed off.
     *
     * @return list<int>
     */
    private function monthsWorthChecking(FinancialYear $financialYear, MonthlyFigures $monthly, CarbonImmutable $today): array
    {
        $months = [];

        if ($financialYear->year === $today->year) {
            $months[] = $today->month;
        }

        for ($month = self::MONTHS; $month >= 1; $month--) {
            if ($monthly->month($month)->status === MonthStatus::Complete) {
                if (! in_array($month, $months, true)) {
                    $months[] = $month;
                }

                break;
            }
        }

        return $months;
    }

    /**
     * ALRT-04: a month the user has moved past was never signed off.
     */
    private function incompleteMonth(FinancialYear $financialYear, MonthlyFigures $monthly, CarbonImmutable $today): ?Alert
    {
        $upTo = $this->lastMonthAlreadyPassed($financialYear, $today);

        for ($month = 1; $month <= $upTo; $month++) {
            if ($monthly->month($month)->status !== MonthStatus::Complete) {
                return new Alert(
                    AlertType::IncompleteMonth,
                    $this->monthName($month),
                    route('months.show', ['year' => $financialYear->year, 'month' => $month]),
                );
            }
        }

        return null;
    }

    /**
     * ALRT-05: a goal will not arrive by the date it was given.
     */
    private function goalOffTrack(FinancialYear $financialYear, CarbonImmutable $today): ?Alert
    {
        foreach ($this->goals->handle($financialYear->user, $financialYear, $today) as $goal) {
            if ($goal->isOffTrack) {
                return new Alert(AlertType::GoalOffTrack, $goal->name, route('goals.index'));
            }
        }

        return null;
    }

    /**
     * The first month the user has not lived through, or null for a year already over.
     */
    private function firstMonthStillToCome(FinancialYear $financialYear, CarbonImmutable $today): ?int
    {
        return match (true) {
            $financialYear->year > $today->year => 1,
            $financialYear->year < $today->year => null,
            default => $today->month,
        };
    }

    /**
     * The last month wholly behind the user. Every month of a year already over, none of
     * one not yet begun.
     */
    private function lastMonthAlreadyPassed(FinancialYear $financialYear, CarbonImmutable $today): int
    {
        return match (true) {
            $financialYear->year < $today->year => self::MONTHS,
            $financialYear->year > $today->year => 0,
            default => $today->month - 1,
        };
    }

    private function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month - 1];
    }

    private function count(int $number): string
    {
        return $number === 1 ? '1 transaction' : sprintf('%d transactions', $number);
    }
}
