<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CategoryFigures;
use App\Data\Variance;
use App\Data\VarianceReport;
use App\Enums\Allocation;
use App\Enums\MonthStatus;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Enums\VarianceStatus;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\Models\UserPreference;

/**
 * How far each category has drifted from its plan, and how much that matters (§7.4).
 *
 * Every comparison is integer arithmetic against a threshold the user sets. The threshold
 * is a percentage, so the obvious form is P × (1 + t) — but that divides, and dividing
 * money invites a rounding argument at the boundary. Multiplying both sides by 100 instead
 * gives the same answer exactly: A × 100 against P × (100 + t).
 */
final readonly class CalculateVariances
{
    private const int MONTHS = 12;

    public function __construct(private CalculateMonthlyFigures $figures) {}

    /**
     * A single month, or the year so far when no month is named.
     *
     * Year-to-date counts only finished months, on both sides, so a part-month is never
     * measured against a whole one (CMP-04).
     */
    public function handle(FinancialYear $financialYear, ?int $month = null): VarianceReport
    {
        $figures = $this->figures->handle($financialYear);
        $threshold = $this->threshold($financialYear);
        $spreadOnly = $this->spreadOnlyCategories($financialYear);

        $completedMonths = [];

        for ($candidate = 1; $candidate <= self::MONTHS; $candidate++) {
            if ($figures->month($candidate)->status === MonthStatus::Complete) {
                $completedMonths[] = $candidate;
            }
        }

        $income = [];
        $expenses = [];

        foreach ($figures->categories as $category) {
            // A category planned entirely by setting money aside each month is judged on
            // the year so far even in a single-month view: the real payment lands in one
            // month, so month-by-month it would look wildly under then wildly over
            // (CMP-05).
            $isSpread = in_array($category->categoryId, $spreadOnly, true);

            $months = match (true) {
                $month === null => $completedMonths,
                $isSpread => range(1, $month),
                default => [$month],
            };

            $variance = $this->varianceFor($category, $months, $threshold, $isSpread);

            if ($category->type === TransactionType::Income) {
                $income[] = $variance;
            } else {
                $expenses[] = $variance;
            }
        }

        return new VarianceReport(
            month: $month,
            completedMonths: count($completedMonths),
            income: $this->sorted($income),
            expenses: $this->sorted($expenses),
        );
    }

    /**
     * @param  list<int>  $months
     */
    private function varianceFor(CategoryFigures $category, array $months, int $threshold, bool $isSpread): Variance
    {
        $planned = 0;
        $actual = 0;

        foreach ($months as $month) {
            $planned += $category->plannedFor($month);
            $actual += $category->actualFor($month);
        }

        return new Variance(
            categoryId: $category->categoryId,
            categoryName: $category->categoryName,
            type: $category->type,
            plannedCents: $planned,
            actualCents: $actual,
            varianceCents: $actual - $planned,
            status: $this->status($category->type, $planned, $actual, $threshold),
            isSpread: $isSpread,
        );
    }

    private function status(TransactionType $type, int $planned, int $actual, int $threshold): VarianceStatus
    {
        if ($planned === 0 && $actual === 0) {
            return VarianceStatus::NoPlan;
        }

        if ($type === TransactionType::Income) {
            return match (true) {
                $actual >= $planned => VarianceStatus::Ok,
                $actual * 100 >= $planned * (100 - $threshold) => VarianceStatus::Warning,
                default => VarianceStatus::Over,
            };
        }

        return match (true) {
            $actual <= $planned => VarianceStatus::Ok,
            $actual * 100 <= $planned * (100 + $threshold) => VarianceStatus::Warning,
            default => VarianceStatus::Over,
        };
    }

    /**
     * Worst first, then biggest first within a status, so the categories worth doing
     * something about are at the top of the screen (CMP-06).
     *
     * @param  list<Variance>  $variances
     * @return list<Variance>
     */
    private function sorted(array $variances): array
    {
        usort($variances, function (Variance $a, Variance $b): int {
            $bySeverity = $a->status->severity() <=> $b->status->severity();

            if ($bySeverity !== 0) {
                return $bySeverity;
            }

            return abs($b->varianceCents) <=> abs($a->varianceCents);
        });

        return $variances;
    }

    /**
     * Categories whose plan comes only from money set aside monthly (CMP-05).
     *
     * @return list<int>
     */
    private function spreadOnlyCategories(FinancialYear $financialYear): array
    {
        $items = PlanItem::query()
            ->where('financial_year_id', $financialYear->id)
            ->get(['category_id', 'kind', 'allocation']);

        $byCategory = [];

        foreach ($items as $item) {
            $isSpread = $item->kind === PlanItemKind::Irregular
                && $item->allocation === Allocation::Spread;

            $byCategory[$item->category_id] = ($byCategory[$item->category_id] ?? true) && $isSpread;
        }

        $spreadOnly = [];

        foreach ($byCategory as $categoryId => $allSpread) {
            if ($allSpread) {
                $spreadOnly[] = $categoryId;
            }
        }

        return $spreadOnly;
    }

    private function threshold(FinancialYear $financialYear): int
    {
        return $financialYear->user->preference->budget_warning_threshold_percent
            ?? UserPreference::DEFAULT_WARNING_THRESHOLD;
    }
}
