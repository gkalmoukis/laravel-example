<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateCashFlow;
use App\Data\BalanceLine;
use App\Models\FinancialYear;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the balance does over the year, under the plan, in reality and in the forecast
 * (CF-01, CF-02).
 */
final readonly class CashFlowController
{
    private const int MONTHS = 12;

    public function index(FinancialYear $year, CalculateCashFlow $cashFlow): Response
    {
        Gate::authorize('view', $year);

        $flow = $cashFlow->handle($year, $year->user->today());

        $months = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $line = $flow->month($month);

            $months[] = [
                'month' => $month,
                'status' => $line->status->value,
                'planned' => $this->line($line->planned),
                'forecast' => $this->line($line->forecast),
                // Null rather than zero past the last month reached: a zero would read as
                // "earned and spent nothing", which is a claim about the future.
                'actual' => $line->actual instanceof BalanceLine ? $this->line($line->actual) : null,
            ];
        }

        return Inertia::render('reports/cash-flow', [
            'year' => $year->year,
            'openingBalanceCents' => $flow->openingBalanceCents,
            'lastActualMonth' => $flow->lastActualMonth,
            'currentAvailableCents' => $flow->currentAvailableCents,
            'plannedYearEndCents' => $flow->plannedYearEndCents(),
            'forecastYearEndCents' => $flow->forecastYearEndCents(),
            'months' => $months,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function line(BalanceLine $line): array
    {
        return [
            'openingCents' => $line->openingCents,
            'incomeCents' => $line->incomeCents,
            'expenseCents' => $line->expenseCents,
            'netCents' => $line->netCents,
            'closingCents' => $line->closingCents,
        ];
    }
}
