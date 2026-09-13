<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateGoalProgress;
use App\Actions\CreateGoal;
use App\Actions\ResolveSelectedYear;
use App\Actions\UpdateGoal;
use App\Data\GoalProgress;
use App\Enums\GoalType;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the user is saving towards, and how it is going (GOAL-01 … GOAL-04).
 */
final readonly class GoalController
{
    public function index(Request $request, #[CurrentUser] User $user, CalculateGoalProgress $calculator): Response
    {
        $year = $this->selectedYear($user);
        $showArchived = $request->boolean('archived');

        $progress = $calculator->handle($user, $year, $user->today());

        $archived = $user->goals()
            ->whereNotNull('archived_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('goals/index', [
            'goals' => array_map($this->present(...), $progress),
            'archived' => $showArchived ? $this->presentArchived($archived) : [],
            'archivedCount' => $archived->count(),
            'showArchived' => $showArchived,
            'years' => $this->years($user),
        ]);
    }

    public function store(StoreGoalRequest $request, #[CurrentUser] User $user, CreateGoal $action): RedirectResponse
    {
        $action->handle($user, $request->goalAttributes());

        return back()->with('status', 'Goal added.');
    }

    public function update(UpdateGoalRequest $request, Goal $goal, UpdateGoal $action): RedirectResponse
    {
        Gate::authorize('update', $goal);

        $action->handle($goal, $request->goalAttributes());

        return back()->with('status', 'Goal updated.');
    }

    /**
     * Goals the user has put away, listed only when they ask to see them (GOAL-04).
     *
     * @param  Collection<int, Goal>  $archived
     * @return list<array<string, mixed>>
     */
    private function presentArchived(Collection $archived): array
    {
        $goals = [];

        foreach ($archived as $goal) {
            $goals[] = [
                'id' => $goal->id,
                'name' => $goal->name,
                'type' => $goal->type->value,
            ];
        }

        return $goals;
    }

    /**
     * The years a year-end-balance goal can be set against.
     *
     * @return list<array{id: int, year: int}>
     */
    private function years(User $user): array
    {
        $years = [];

        foreach ($user->financialYears()->orderByDesc('year')->get() as $year) {
            $years[] = ['id' => $year->id, 'year' => $year->year];
        }

        return $years;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(GoalProgress $progress): array
    {
        return [
            'id' => $progress->goalId,
            'name' => $progress->name,
            'type' => $progress->type->value,
            'targetCents' => $progress->targetCents,
            'currentCents' => $progress->currentCents,
            'remainingCents' => $progress->remainingCents,
            'monthlyContributionCents' => $progress->monthlyContributionCents,
            'targetDate' => $progress->targetDate?->toDateString(),
            'estimatedCompletion' => $progress->estimatedCompletion?->toDateString(),
            'isReached' => $progress->isReached,
            'isOffTrack' => $progress->isOffTrack,
            'isTracked' => $progress->isTracked,
            // The emergency fund has its own screen, and no goal can be removed from
            // under the user (GOAL-01).
            'canArchive' => $progress->type !== GoalType::EmergencyFund,
        ];
    }

    private function selectedYear(User $user): ?FinancialYear
    {
        $years = $user->financialYears()->orderByDesc('year')->get();

        $selected = resolve(ResolveSelectedYear::class)->handle($user, $years, null);

        return $years->firstWhere('year', $selected);
    }
}
