<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\GoalProgress;
use App\Enums\GoalType;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Where each goal stands, and whether it will get there in time (§7.7).
 *
 * Two kinds of goal already have an answer elsewhere in the application, so they read it
 * rather than asking the user to keep a copy: the emergency fund from what is set aside,
 * and a year-end balance from the forecast. The rest are amounts the user maintains.
 */
final readonly class CalculateGoalProgress
{
    /**
     * A contribution can be small enough that the goal is centuries away. Past this the
     * honest answer is that it is not going to happen on this plan.
     */
    private const int FURTHEST_REACHABLE_MONTH = 600;

    public function __construct(
        private CalculateEmergencyFund $emergencyFund,
        private CalculateCashFlow $cashFlow,
    ) {}

    /**
     * @return list<GoalProgress>
     */
    public function handle(User $user, ?FinancialYear $selectedYear, CarbonImmutable $today): array
    {
        $goals = $user->goals()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        $progress = [];

        foreach ($goals as $goal) {
            $progress[] = $this->forGoal($goal, $selectedYear, $today);
        }

        return $progress;
    }

    public function forGoal(Goal $goal, ?FinancialYear $selectedYear, CarbonImmutable $today): GoalProgress
    {
        $target = $goal->target_amount_cents->cents ?? 0;
        $contribution = $goal->monthly_contribution_cents->cents ?? 0;

        [$current, $isTracked, $target] = $this->currentAndTarget($goal, $selectedYear, $today, $target);

        $remaining = max(0, $target - $current);
        $estimated = $this->estimatedCompletion($goal, $remaining, $contribution, $today, $selectedYear, $current, $target);

        return new GoalProgress(
            goalId: $goal->id,
            name: $goal->name,
            type: $goal->type,
            targetCents: $target,
            currentCents: $current,
            remainingCents: $remaining,
            monthlyContributionCents: $contribution,
            targetDate: $this->targetDate($goal),
            estimatedCompletion: $estimated,
            isReached: $remaining === 0,
            isOffTrack: $this->isOffTrack($goal, $remaining, $contribution, $estimated),
            isTracked: $isTracked,
        );
    }

    /**
     * What the goal is worth now, and what it is aiming at.
     *
     * The emergency fund also computes its own target, which is why the target comes back
     * from here rather than being read once up front.
     *
     * @return array{0: int, 1: bool, 2: int}
     */
    private function currentAndTarget(Goal $goal, ?FinancialYear $selectedYear, CarbonImmutable $today, int $target): array
    {
        if ($goal->type === GoalType::EmergencyFund) {
            if (! $selectedYear instanceof FinancialYear) {
                return [0, true, $target];
            }

            $status = $this->emergencyFund->handle($selectedYear, $today);

            return [$status->currentCents, true, $status->targetCents];
        }

        if ($goal->type === GoalType::YearEndBalance) {
            $year = $goal->financialYear ?? $selectedYear;

            if (! $year instanceof FinancialYear) {
                return [0, true, $target];
            }

            return [$this->cashFlow->handle($year, $today)->forecastYearEndCents(), true, $target];
        }

        return [$goal->current_amount_cents->cents, false, $target];
    }

    /**
     * When the goal will be met.
     *
     * A year-end balance is not reached by saving towards it — it is whatever the year
     * leaves behind — so its answer is the last day of its year, or nothing at all when
     * the forecast does not get there (§7.7).
     */
    private function estimatedCompletion(
        Goal $goal,
        int $remaining,
        int $contribution,
        CarbonImmutable $today,
        ?FinancialYear $selectedYear,
        int $current,
        int $target,
    ): ?CarbonImmutable {
        if ($goal->type === GoalType::YearEndBalance) {
            $year = $goal->financialYear ?? $selectedYear;

            if (! $year instanceof FinancialYear || $current < $target) {
                return null;
            }

            return $today->setDate($year->year, 12, 31)->startOfDay();
        }

        if ($remaining === 0) {
            return $today->startOfMonth();
        }

        if ($contribution <= 0) {
            return null;
        }

        $months = intdiv($remaining, $contribution) + ($remaining % $contribution === 0 ? 0 : 1);

        if ($months > self::FURTHEST_REACHABLE_MONTH) {
            return null;
        }

        return $today->startOfMonth()->addMonths($months);
    }

    /**
     * Whether the goal will miss the date the user set for it.
     *
     * A goal with no date cannot be late. A goal with a date and nothing going in is late
     * by definition, because nothing will change before the date arrives.
     */
    private function isOffTrack(Goal $goal, int $remaining, int $contribution, ?CarbonImmutable $estimated): bool
    {
        $targetDate = $this->targetDate($goal);

        if (! $targetDate instanceof CarbonImmutable || $remaining === 0) {
            return false;
        }

        if ($contribution <= 0 && $goal->type !== GoalType::YearEndBalance) {
            return true;
        }

        return ! $estimated instanceof CarbonImmutable || $estimated->greaterThan($targetDate);
    }

    private function targetDate(Goal $goal): ?CarbonImmutable
    {
        $date = $goal->target_date;

        return $date === null ? null : CarbonImmutable::parse($date->toDateString());
    }
}
