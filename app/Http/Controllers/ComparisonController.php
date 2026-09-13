<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateMonthlyFigures;
use App\Actions\CalculateVariances;
use App\Data\MonthlyFigures;
use App\Data\Variance;
use App\Enums\MonthStatus;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan against actual, for one month or for the year so far (CMP-03, CMP-04).
 *
 * The two modes answer different questions. A month asks "how did March go"; year to date
 * asks "how is the year going", and counts only months the user has signed off, because
 * a part-month dragged into the total would make the year look better than it is.
 */
final readonly class ComparisonController
{
    private const int MONTHS = 12;

    public function index(
        Request $request,
        FinancialYear $year,
        CalculateMonthlyFigures $figures,
        CalculateVariances $variances,
    ): Response {
        Gate::authorize('view', $year);

        $monthly = $figures->handle($year);

        $isYearToDate = $request->string('mode')->value() === 'ytd';
        $month = $isYearToDate ? null : $this->selectedMonth($request, $year, $monthly);

        $report = $variances->handle($year, $month);
        $breakdown = $this->breakdown($year, $month, $monthly);

        return Inertia::render('comparison/index', [
            'year' => $year->year,
            'mode' => $isYearToDate ? 'ytd' : 'month',
            'month' => $month,
            'completedMonths' => $report->completedMonths,
            'income' => $this->present($report->income, $breakdown),
            'expenses' => $this->present($report->expenses, $breakdown),
        ]);
    }

    /**
     * Which month the page opens on (CMP-03).
     *
     * The latest month the user has signed off is the one they were last working on. With
     * none signed off, the month they are living through is the next best guess, and a
     * year they are not living in falls back to its first month.
     */
    private function selectedMonth(Request $request, FinancialYear $year, MonthlyFigures $monthly): int
    {
        $requested = $request->integer('month');

        if ($requested >= 1 && $requested <= self::MONTHS) {
            return $requested;
        }

        for ($month = self::MONTHS; $month >= 1; $month--) {
            if ($monthly->month($month)->status === MonthStatus::Complete) {
                return $month;
            }
        }

        $today = $year->user->today();

        return $today->year === $year->year ? $today->month : 1;
    }

    /**
     * @param  list<Variance>  $variances
     * @param  array<int, list<array<string, mixed>>>  $breakdown
     * @return list<array<string, mixed>>
     */
    private function present(array $variances, array $breakdown): array
    {
        return array_map(fn (Variance $variance): array => [
            'categoryId' => $variance->categoryId,
            'categoryName' => $variance->categoryName,
            'plannedCents' => $variance->plannedCents,
            'actualCents' => $variance->actualCents,
            'varianceCents' => $variance->varianceCents,
            'status' => $variance->status->value,
            'label' => $variance->label(),
            'isSpread' => $variance->isSpread,
            'subcategories' => $breakdown[$variance->categoryId] ?? [],
        ], $variances);
    }

    /**
     * What each category breaks down into over the period being shown (CAT-08).
     *
     * Year to date covers the signed-off months, so the breakdown has to cover exactly
     * those months too — otherwise the parts would not add up to the whole above them.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function breakdown(FinancialYear $year, ?int $month, MonthlyFigures $monthly): array
    {
        $query = Transaction::valid()
            ->where('transactions.user_id', $year->user_id)
            ->whereYear('occurred_on', $year->year);

        if ($month !== null) {
            $query->whereMonth('occurred_on', $month);
        } else {
            $completed = [];

            for ($candidate = 1; $candidate <= self::MONTHS; $candidate++) {
                if ($monthly->month($candidate)->status === MonthStatus::Complete) {
                    $completed[] = $candidate;
                }
            }

            $query->whereIn(Transaction::query()->raw('MONTH(occurred_on)'), $completed === [] ? [0] : $completed);
        }

        $rows = $query
            ->groupBy('category_id', 'subcategory_id')
            ->get([
                'category_id',
                'subcategory_id',
                Transaction::query()->raw('SUM(amount_cents) as total_cents'),
            ]);

        $names = Category::query()
            ->where('user_id', $year->user_id)
            ->whereNotNull('parent_id')
            ->pluck('name', 'id');

        $breakdown = [];

        foreach ($rows as $row) {
            $subcategoryId = $row->getAttribute('subcategory_id');
            $name = $subcategoryId === null ? null : $names->get($this->toInt($subcategoryId));

            $breakdown[$this->toInt($row->getAttribute('category_id'))][] = [
                'id' => $subcategoryId === null ? null : $this->toInt($subcategoryId),
                'name' => is_string($name) ? $name : 'No subcategory',
                'actualCents' => $this->toInt($row->getAttribute('total_cents')),
            ];
        }

        return $breakdown;
    }

    private function toInt(mixed $value): int
    {
        return (int) (is_numeric($value) ? $value : 0);
    }
}
