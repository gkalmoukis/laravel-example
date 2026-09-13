<?php

declare(strict_types=1);

namespace App\Data;

/**
 * The home screen in figures (DASH-01 … DASH-05).
 *
 * Composed from the same Actions the detail screens use rather than computed again here,
 * so a card can never disagree with the page it links to. Nothing is stored.
 */
final readonly class DashboardData
{
    /**
     * @param  list<Alert>  $alerts
     */
    public function __construct(
        public int $year,
        public int $currentAvailableCents,
        public AnnualSummary $summary,
        public EmergencyFundStatus $emergencyFund,
        public NetWorthPosition $netWorth,
        public TransactionIssueCount $issues,
        public array $alerts,
    ) {}
}
