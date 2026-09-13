<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;

/**
 * Net worth from the opening position through December, for the dashboard (DASH-04, NW-01).
 */
final readonly class BuildNetWorthTrendChart
{
    public function __construct(private CalculateNetWorth $netWorth) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(FinancialYear $financialYear): array
    {
        $position = $this->netWorth->handle($financialYear);

        $points = [];

        foreach ($position->months as $month) {
            $points[] = [
                'month' => $month->month,
                'netCents' => $month->netCents(),
                // Beyond this the figures are the last known ones carried forward, which
                // is a weaker claim and is drawn as one (NW-04).
                'isRecorded' => $position->latestRecordedMonth !== null
                    && $month->month <= $position->latestRecordedMonth,
            ];
        }

        return $points;
    }
}
