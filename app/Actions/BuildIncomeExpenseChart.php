<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MonthStatus;
use App\Models\FinancialYear;

/**
 * Income and expenses month by month, said plainly: a finished month reports what
 * happened, an unfinished one what is expected (DASH-04).
 *
 * Which of the two a month is showing travels with it, so the interface can tell them
 * apart rather than presenting a forecast as a fact.
 */
final readonly class BuildIncomeExpenseChart
{
    private const int MONTHS = 12;

    public function __construct(private CalculateMonthlyFigures $figures) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(FinancialYear $financialYear): array
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
}
