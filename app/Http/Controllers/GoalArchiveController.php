<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ArchiveGoal;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Putting a goal away, and taking it back out (GOAL-04).
 */
final readonly class GoalArchiveController
{
    public function store(Goal $goal, ArchiveGoal $action): RedirectResponse
    {
        // Guarded by delete rather than update: the emergency fund goal cannot be put
        // away any more than it can be removed (GOAL-01).
        Gate::authorize('delete', $goal);

        $action->handle($goal);

        return back()->with('status', 'Goal archived.');
    }

    public function destroy(Goal $goal, ArchiveGoal $action): RedirectResponse
    {
        Gate::authorize('delete', $goal);

        $action->handle($goal, false);

        return back()->with('status', 'Goal restored.');
    }
}
