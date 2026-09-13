<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\MonthlyFigures;
use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;

/**
 * What each top-level category planned to spend in the month on show, against what it did
 * spend (DASH-04).
 *
 * Categories with nothing on either side are left out: a bar chart of zeroes hides the
 * handful of bars worth looking at.
 */
final readonly class BuildExpensesByCategoryChart
{
    private const int MONTHS = 12;

    public function __construct(private CalculateMonthlyFigures $figures) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(FinancialYear $financialYear, CarbonImmutable $today): array
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
     * Which month the chart shows: the latest one signed off, else the one being lived
     * through, else the first (CMP-03).
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
