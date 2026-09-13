<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\EmergencyFundStatus;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\NetWorthSnapshot;
use App\Models\PlanItemAmount;
use Carbon\CarbonImmutable;

/**
 * Where the emergency fund stands, and when it will be there (§7.6).
 *
 * The target answers "how long could I keep going if the money stopped", so it is built
 * from the expenses the user could not simply stop paying — the categories they marked
 * essential — rather than from everything they spend.
 */
final readonly class CalculateEmergencyFund
{
    private const int MONTHS = 12;

    /**
     * A contribution can be small enough that the target is decades away. Past this the
     * honest answer is that the current plan does not get there (§7.6).
     */
    private const int FURTHEST_REACHABLE_MONTH = 600;

    public function handle(FinancialYear $financialYear, CarbonImmutable $today): EmergencyFundStatus
    {
        $goal = $this->goal($financialYear);

        $essentialMonthly = $this->essentialMonthlyCents($financialYear);
        $monthsOfCover = $financialYear->user->preferences()->emergency_fund_months;

        $customTarget = $this->customTargetCents($goal);

        $target = $customTarget ?? $monthsOfCover * $essentialMonthly;

        [$current, $hasBeenStarted] = $this->currentCents($financialYear, $today);

        $contribution = $this->contributionCents($goal);
        $remaining = max(0, $target - $current);

        return new EmergencyFundStatus(
            essentialMonthlyCents: $essentialMonthly,
            monthsOfCover: $monthsOfCover,
            targetCents: $target,
            targetIsCustom: $customTarget !== null,
            currentCents: $current,
            remainingCents: $remaining,
            monthlyContributionCents: $contribution,
            monthsToTarget: $this->monthsToTarget($remaining, $contribution),
            isReached: $remaining === 0,
            projectedYearEndCents: $this->projectedYearEnd($current, $target, $contribution, $financialYear, $today),
            hasBeenStarted: $hasBeenStarted,
        );
    }

    /**
     * The target the user set by hand, if they set one.
     *
     * Someone with an irregular income may know their own number better than a multiple
     * of their essential spending does (§7.6).
     */
    private function customTargetCents(?Goal $goal): ?int
    {
        if (! $goal instanceof Goal || ! $goal->target_is_custom) {
            return null;
        }

        return $goal->target_amount_cents?->cents;
    }

    /**
     * No goal at all, and a goal nobody set a contribution on, mean the same thing here:
     * nothing is going in. `??` covers both — it suppresses the null dereference as well
     * as the null value, which is what makes `?->` before it redundant.
     */
    private function contributionCents(?Goal $goal): int
    {
        return $goal->monthly_contribution_cents->cents ?? 0;
    }

    /**
     * What a month of essential living costs, from the plan (§7.6).
     *
     * Divided with integer division, so the figure never claims a precision the plan does
     * not have.
     */
    private function essentialMonthlyCents(FinancialYear $financialYear): int
    {
        $total = PlanItemAmount::query()
            ->join('plan_items', 'plan_items.id', '=', 'plan_item_amounts.plan_item_id')
            ->join('categories', 'categories.id', '=', 'plan_items.category_id')
            ->where('plan_items.financial_year_id', $financialYear->id)
            ->where('categories.is_essential', true)
            ->sum('plan_item_amounts.amount_cents');

        return intdiv((int) $total, self::MONTHS);
    }

    /**
     * What is actually set aside: the latest figure the user has given for each emergency
     * fund holding, which may be several months old (§7.6, §7.8).
     *
     * @return array{0: int, 1: bool} the total, and whether anything has been recorded
     */
    private function currentCents(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $upToMonth = $today->year > $financialYear->year ? self::MONTHS : $today->month;

        $snapshots = NetWorthSnapshot::query()
            ->where('financial_year_id', $financialYear->id)
            ->where('month', '<=', $today->year < $financialYear->year ? NetWorthSnapshot::OPENING_MONTH : $upToMonth)
            ->whereHas('netWorthItem', fn ($query) => $query
                ->where('user_id', $financialYear->user_id)
                ->where('is_active', true)
                ->where('kind', NetWorthItemKind::EmergencyFund))
            ->orderBy('month')
            ->get();

        $latest = [];

        foreach ($snapshots as $snapshot) {
            // Ordered by month, so the last write per holding is its latest value.
            $latest[$snapshot->net_worth_item_id] = $snapshot->value_cents->cents;
        }

        $total = array_sum($latest);

        // Month 0 exists for every holding and starts at zero, so "has anything been set
        // aside" is about the figures, not about whether rows exist (EF-03).
        return [$total, $total > 0];
    }

    /**
     * How many months until the target is met, or null when the plan never gets there.
     */
    private function monthsToTarget(int $remaining, int $contribution): ?int
    {
        if ($remaining === 0) {
            return 0;
        }

        if ($contribution <= 0) {
            return null;
        }

        $months = intdiv($remaining, $contribution) + ($remaining % $contribution === 0 ? 0 : 1);

        return $months > self::FURTHEST_REACHABLE_MONTH ? null : $months;
    }

    /**
     * What will be set aside by the end of the year, capped at the target: money beyond
     * it is no longer emergency fund, it is just savings (§7.6).
     */
    private function projectedYearEnd(
        int $current,
        int $target,
        int $contribution,
        FinancialYear $financialYear,
        CarbonImmutable $today,
    ): int {
        $monthsLeft = match (true) {
            $today->year < $financialYear->year => self::MONTHS,
            $today->year > $financialYear->year => 0,
            default => self::MONTHS - $today->month,
        };

        return min($target, $current + $monthsLeft * $contribution);
    }

    private function goal(FinancialYear $financialYear): ?Goal
    {
        return $financialYear->user->goals()
            ->where('type', GoalType::EmergencyFund)
            ->whereNull('archived_at')
            ->first();
    }
}
