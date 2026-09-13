<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\MonthlyFigures;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;

/**
 * The five pictures on the home screen (DASH-04).
 *
 * Each is built on its own so it can be deferred on its own: the page paints its figures
 * first and the charts arrive after, rather than all six calculations holding up the one
 * thing the user came to read.
 *
 * Every series is integer cents. Nothing here rounds, scales or averages — the interface
 * formats, and a chart that quietly rounded would disagree with the table beside it.
 */
final readonly class BuildDashboardCharts
{
    private const int MONTHS = 12;

    public function __construct(
        private CalculateMonthlyFigures $figures,
        private CalculateCashFlow $cashFlow,
        private CalculateNetWorth $netWorth,
        private CalculateGoalProgress $goals,
    ) {}

    /**
     * Closing balance, plan against actual against forecast (CF-02).
     *
     * The actual line stops where the record stops rather than dropping to zero: a month
     * nobody has reached is missing, not empty.
     *
     * @return list<array<string, mixed>>
     */
    public function closingBalance(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $flow = $this->cashFlow->handle($financialYear, $today);

        $points = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $line = $flow->month($month);

            $points[] = [
                'month' => $month,
                'plannedCents' => $line->planned->closingCents,
                'forecastCents' => $line->forecast->closingCents,
                'actualCents' => $line->actual?->closingCents,
            ];
        }

        return $points;
    }

    /**
     * What each top-level category planned to spend in the month on show, against what it
     * did spend.
     *
     * Categories with nothing on either side are left out: a bar chart of zeroes hides
     * the handful of bars worth looking at.
     *
     * @return array<string, mixed>
     */
    public function expensesByCategory(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $monthly = $this->figures->handle($financialYear);
        $month = $this->monthOnShow($monthly, $financialYear, $today);

        $rows = [];

        foreach ($monthly->categoriesOfType(TransactionType::Expense) as $category) {
            $planned = $category->plannedFor($month);
            $actual = $category->actualFor($month);

            if ($planned === 0 && $actual === 0) {
                continue;
            }

            $rows[] = [
                'categoryId' => $category->categoryId,
                'categoryName' => $category->categoryName,
                'plannedCents' => $planned,
                'actualCents' => $actual,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['actualCents'] <=> $a['actualCents']);

        return ['month' => $month, 'rows' => $rows];
    }

    /**
     * Income and expenses month by month, said plainly: a finished month reports what
     * happened, an unfinished one what is expected (DASH-04).
     *
     * Which of the two a month is showing travels with it, so the interface can tell them
     * apart rather than presenting a forecast as a fact.
     *
     * @return list<array<string, mixed>>
     */
    public function incomeAndExpenses(FinancialYear $financialYear): array
    {
        $monthly = $this->figures->handle($financialYear);

        $points = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $totals = $monthly->month($month);
            $isActual = $totals->status === MonthStatus::Complete;

            $points[] = [
                'month' => $month,
                'incomeCents' => $isActual ? $totals->actualIncomeCents : $totals->forecastIncomeCents,
                'expenseCents' => $isActual ? $totals->actualExpenseCents : $totals->forecastExpenseCents,
                'isActual' => $isActual,
            ];
        }

        return $points;
    }

    /**
     * Net worth from the opening position through December (NW-01).
     *
     * @return list<array<string, mixed>>
     */
    public function netWorthTrend(FinancialYear $financialYear): array
    {
        $position = $this->netWorth->handle($financialYear);

        $points = [];

        foreach ($position->months as $month) {
            $points[] = [
                'month' => $month->month,
                'netCents' => $month->netCents(),
                // Beyond this the figures are the last known ones carried forward, which
                // is a weaker claim and is drawn as one (NW-04).
                'isRecorded' => $position->latestRecordedMonth !== null
                    && $month->month <= $position->latestRecordedMonth,
            ];
        }

        return $points;
    }

    /**
     * How far along each goal is (GOAL-02).
     *
     * @return list<array<string, mixed>>
     */
    public function goalsProgress(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $rows = [];

        foreach ($this->goals->handle($financialYear->user, $financialYear, $today) as $goal) {
            $rows[] = [
                'goalId' => $goal->goalId,
                'name' => $goal->name,
                'currentCents' => $goal->currentCents,
                'targetCents' => $goal->targetCents,
                'isOffTrack' => $goal->isOffTrack,
                'isReached' => $goal->isReached,
            ];
        }

        return $rows;
    }

    /**
     * Which month the expenses chart shows: the latest one signed off, else the one being
     * lived through, else the first (CMP-03).
     */
    private function monthOnShow(MonthlyFigures $monthly, FinancialYear $financialYear, CarbonImmutable $today): int
    {
        for ($month = self::MONTHS; $month >= 1; $month--) {
            if ($monthly->month($month)->status === MonthStatus::Complete) {
                return $month;
            }
        }

        return $today->year === $financialYear->year ? $today->month : 1;
    }
}
