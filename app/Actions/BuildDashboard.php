<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\DashboardData;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;

/**
 * Everything the home screen shows, in one pass (DASH-01 … DASH-05).
 *
 * Each figure comes from the Action that owns it, so the dashboard is a view onto the
 * rest of the application rather than a second implementation of it. Nothing is cached:
 * a corrected transaction changes every card on the next request.
 */
final readonly class BuildDashboard
{
    public function __construct(
        private CalculateCashFlow $cashFlow,
        private CalculateAnnualSummary $annualSummary,
        private CalculateEmergencyFund $emergencyFund,
        private CalculateNetWorth $netWorth,
        private CountTransactionIssues $issues,
        private BuildAlerts $alerts,
    ) {}

    public function handle(FinancialYear $financialYear, CarbonImmutable $today): DashboardData
    {
        return new DashboardData(
            year: $financialYear->year,
            currentAvailableCents: $this->cashFlow->handle($financialYear, $today)->currentAvailableCents,
            summary: $this->annualSummary->handle($financialYear, $today),
            emergencyFund: $this->emergencyFund->handle($financialYear, $today),
            netWorth: $this->netWorth->handle($financialYear),
            issues: $this->issues->handle($financialYear),
            alerts: $this->alerts->handle($financialYear, $today),
        );
    }
}
