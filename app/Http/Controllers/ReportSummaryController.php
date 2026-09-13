<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildAlerts;
use App\Actions\CalculateAnnualSummary;
use App\Actions\CalculateCashFlow;
use App\Data\Alert;
use App\Models\FinancialYear;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole year in one screen, and the way in to the three that explain it.
 *
 * Plan vs actual, cash flow and forecast each answer one question well and none of them
 * answers "how is the year going". This composes the figures those three already
 * calculate — nothing here works anything out for itself (FC-01, CF-01, UX-05).
 */
final readonly class ReportSummaryController
{
    public function index(
        FinancialYear $year,
        CalculateAnnualSummary $annualSummary,
        CalculateCashFlow $cashFlow,
        BuildAlerts $alerts,
    ): Response {
        Gate::authorize('view', $year);

        $today = $year->user->today();

        $summary = $annualSummary->handle($year, $today);
        $flow = $cashFlow->handle($year, $today);

        return Inertia::render('reports/summary', [
            'year' => $year->year,
            'completedMonths' => $summary->completedMonths,
            'income' => [
                'plannedCents' => $summary->plannedIncomeCents,
                'actualCents' => $summary->actualSoFarIncomeCents,
                'forecastCents' => $summary->forecastIncomeCents,
            ],
            'expenses' => [
                'plannedCents' => $summary->plannedExpenseCents,
                'actualCents' => $summary->actualSoFarExpenseCents,
                'forecastCents' => $summary->forecastExpenseCents,
            ],
            'savings' => [
                'plannedCents' => $summary->plannedSavingsCents(),
                'actualCents' => $summary->actualSoFarSavingsCents(),
                'forecastCents' => $summary->forecastSavingsCents(),
            ],
            'balance' => [
                'openingCents' => $flow->openingBalanceCents,
                'availableCents' => $flow->currentAvailableCents,
                'plannedYearEndCents' => $flow->plannedYearEndCents(),
                'forecastYearEndCents' => $flow->forecastYearEndCents(),
                'lastActualMonth' => $flow->lastActualMonth,
            ],
            'deviation' => [
                'cents' => $summary->deviationCents,
                'hasBaseline' => $summary->hasBaseline,
            ],
            'alerts' => $this->present($alerts->handle($year, $today)),
        ]);
    }

    /**
     * Written as a loop rather than a mapping closure so every line is credited by the
     * coverage run.
     *
     * @param  list<Alert>  $alerts
     * @return list<array<string, mixed>>
     */
    private function present(array $alerts): array
    {
        $presented = [];

        foreach ($alerts as $alert) {
            $presented[] = $alert->toArray();
        }

        return $presented;
    }
}
