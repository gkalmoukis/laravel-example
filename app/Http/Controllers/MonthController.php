<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateMonthlyFigures;
use App\Models\FinancialYear;
use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class MonthController
{
    private const int MONTHS = 12;

    /**
     * The year at a glance: twelve cards saying what each month came to and whether it has
     * been finished (MON-02).
     */
    public function index(FinancialYear $year, CalculateMonthlyFigures $figures): Response
    {
        Gate::authorize('view', $year);

        $monthly = $figures->handle($year);
        $issues = $this->issueCounts($year);

        $months = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $totals = $monthly->month($month);

            $months[] = [
                'month' => $month,
                'status' => $totals->status->value,
                'incomeCents' => $totals->actualIncomeCents,
                'expenseCents' => $totals->actualExpenseCents,
                'netCents' => $totals->actualNetCents(),
                'issueCount' => $issues[$month] ?? 0,
            ];
        }

        return Inertia::render('months/index', [
            'year' => $year->year,
            'months' => $months,
        ]);
    }

    /**
     * How many transactions in each month still need putting right (MON-02, TXV-05).
     *
     * One grouped query rather than one per card.
     *
     * @return array<int, int>
     */
    private function issueCounts(FinancialYear $year): array
    {
        $rows = Transaction::flagged()
            ->where('transactions.user_id', $year->user_id)
            ->whereYear('occurred_on', $year->year)
            ->groupBy('month')
            ->get([
                Transaction::query()->raw('MONTH(occurred_on) as month'),
                Transaction::query()->raw('COUNT(*) as flagged_count'),
            ]);

        $counts = [];

        foreach ($rows as $row) {
            $month = $row->getAttribute('month');
            $count = $row->getAttribute('flagged_count');

            $counts[(int) (is_numeric($month) ? $month : 0)] = (int) (is_numeric($count) ? $count : 0);
        }

        return $counts;
    }
}
