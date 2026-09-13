<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateEmergencyFund;
use App\Actions\ResolveSelectedYear;
use App\Actions\UpdateEmergencyFundSettings;
use App\Enums\GoalType;
use App\Enums\TransactionType;
use App\Http\Requests\UpdateEmergencyFundRequest;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How long the user could keep going if their income stopped (EF-01 … EF-03).
 */
final readonly class EmergencyFundController
{
    public function show(#[CurrentUser] User $user, CalculateEmergencyFund $calculator): Response
    {
        $year = $this->selectedYear($user);

        // Without a plan there is nothing to work a target out from, so the screen asks
        // for one rather than showing a target of nothing.
        if (! $year instanceof FinancialYear) {
            return Inertia::render('goals/emergency-fund', [
                'hasYear' => false,
                'year' => null,
                'status' => null,
                'goal' => null,
                'categories' => [],
            ]);
        }

        $status = $calculator->handle($year, $user->today());
        $goal = $this->goal($user);

        return Inertia::render('goals/emergency-fund', [
            'hasYear' => true,
            'year' => $year->year,
            'status' => [
                'essentialMonthlyCents' => $status->essentialMonthlyCents,
                'monthsOfCover' => $status->monthsOfCover,
                'targetCents' => $status->targetCents,
                'targetIsCustom' => $status->targetIsCustom,
                'currentCents' => $status->currentCents,
                'remainingCents' => $status->remainingCents,
                'monthlyContributionCents' => $status->monthlyContributionCents,
                'monthsToTarget' => $status->monthsToTarget,
                'isReached' => $status->isReached,
                'projectedYearEndCents' => $status->projectedYearEndCents,
                'hasBeenStarted' => $status->hasBeenStarted,
            ],
            'goal' => [
                'customTargetCents' => $goal->target_is_custom ? $goal->target_amount_cents?->cents : null,
                'monthlyContributionCents' => $goal->monthly_contribution_cents?->cents,
            ],
            'categories' => $this->essentialChoices($user),
        ]);
    }

    public function update(
        UpdateEmergencyFundRequest $request,
        #[CurrentUser] User $user,
        UpdateEmergencyFundSettings $action,
    ): RedirectResponse {
        $action->handle(
            $user,
            $request->integer('months_of_cover'),
            $request->essentialCategoryIds(),
            $request->customTarget(),
            $request->monthlyContribution(),
        );

        return back()->with('status', 'Emergency fund updated.');
    }

    /**
     * Which expense categories the user would still have to pay (EF-02, CAT-06).
     *
     * @return list<array<string, mixed>>
     */
    private function essentialChoices(User $user): array
    {
        $categories = $user->categories()
            ->where('type', TransactionType::Expense)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $choices = [];

        foreach ($categories as $category) {
            $choices[] = [
                'id' => $category->id,
                'name' => $category->name,
                'isEssential' => $category->is_essential,
            ];
        }

        return $choices;
    }

    private function goal(User $user): Goal
    {
        return $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();
    }

    /**
     * The fund is a user-level thing, but its target comes from a year's plan, so the
     * screen follows whichever year the rest of the app is showing (YEAR-07).
     */
    private function selectedYear(User $user): ?FinancialYear
    {
        $years = $user->financialYears()->orderByDesc('year')->get();

        $selected = resolve(ResolveSelectedYear::class)->handle($user, $years, null);

        return $years->firstWhere('year', $selected);
    }
}
