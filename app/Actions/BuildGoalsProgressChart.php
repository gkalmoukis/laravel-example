<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use Carbon\CarbonImmutable;

/**
 * How far along each goal is, for the dashboard (DASH-04, GOAL-02).
 */
final readonly class BuildGoalsProgressChart
{
    public function __construct(private CalculateGoalProgress $goals) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $rows = [];

        foreach ($this->goals->handle($financialYear->user, $financialYear, $today) as $goal) {
            $rows[] = [
                'goalId' => $goal->goalId,
                'name' => $goal->name,
                'currentCents' => $goal->currentCents,
                'targetCents' => $goal->targetCents,
                'isOffTrack' => $goal->isOffTrack,
                'isReached' => $goal->isReached,
            ];
        }

        return $rows;
    }
}
