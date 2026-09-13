<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildDashboard;
use App\Actions\ResolveSelectedYear;
use App\Data\Alert;
use App\Data\DashboardData;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home screen: where the year stands, in six cards (DASH-01 … DASH-06).
 *
 * Every card links to the screen that explains it, because a figure the user cannot
 * interrogate is a figure they have to take on trust (UX-09).
 */
final readonly class DashboardController
{
    public function __construct(private ResolveSelectedYear $selectedYear) {}

    public function index(#[CurrentUser] User $user, BuildDashboard $dashboard): RedirectResponse|Response
    {
        $year = $this->year($user);

        // Nothing to show without a plan, so a new account is sent to make one first
        // rather than shown six empty cards (YEAR-08).
        if (! $year instanceof FinancialYear) {
            return to_route('financial-years.create');
        }

        return Inertia::render('dashboard', $this->props($dashboard->handle($year, $user->today())));
    }

    /**
     * Money crosses the wire as integer cents and the rates are divided on the client, so
     * nothing here rounds a figure the interface will round again (§7, FE-18).
     *
     * @return array<string, mixed>
     */
    private function props(DashboardData $data): array
    {
        $summary = $data->summary;
        $fund = $data->emergencyFund;

        return [
            'year' => $data->year,
            'currentAvailableCents' => $data->currentAvailableCents,

            'yearEnd' => [
                'forecastCents' => $summary->forecastYearEndCents,
                'plannedCents' => $summary->plannedYearEndCents,
                'deviationCents' => $summary->deviationCents,
                'hasBaseline' => $summary->hasBaseline,
            ],

            'income' => [
                'actualCents' => $summary->actualSoFarIncomeCents,
                'plannedCents' => $summary->plannedIncomeCents,
                'forecastCents' => $summary->forecastIncomeCents,
            ],

            'expenses' => [
                'actualCents' => $summary->actualSoFarExpenseCents,
                'plannedCents' => $summary->plannedExpenseCents,
                'forecastCents' => $summary->forecastExpenseCents,
            ],

            'savings' => [
                'actualCents' => $summary->actualSoFarSavingsCents(),
                'plannedCents' => $summary->plannedSavingsCents(),
                'forecastCents' => $summary->forecastSavingsCents(),
                // The rate is a ratio, not a percentage: full precision until display.
                'actualIncomeCents' => $summary->actualSoFarIncomeCents,
                'plannedIncomeCents' => $summary->plannedIncomeCents,
            ],

            'emergencyFund' => [
                'currentCents' => $fund->currentCents,
                'targetCents' => $fund->targetCents,
                'isReached' => $fund->isReached,
                'hasBeenStarted' => $fund->hasBeenStarted,
                'monthsToTarget' => $fund->monthsToTarget,
            ],

            'netWorth' => [
                'currentCents' => $data->netWorth->current()->netCents(),
                'changeCents' => $data->netWorth->changeVsPreviousMonth(),
            ],

            'completedMonths' => $summary->completedMonths,
            // Shown only when there is something to show (DASH-03).
            'transactionIssues' => $data->issues->total,

            'alerts' => array_map(fn (Alert $alert): array => $alert->toArray(), $data->alerts),
        ];
    }

    private function year(User $user): ?FinancialYear
    {
        $years = $user->financialYears()->orderByDesc('year')->get();

        $selected = $this->selectedYear->handle($user, $years, null);

        return $years->firstWhere('year', $selected);
    }
}
