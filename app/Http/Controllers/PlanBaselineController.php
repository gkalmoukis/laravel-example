<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CapturePlanBaseline;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class PlanBaselineController
{
    /**
     * Re-freezes the plan as the new point of comparison, which the user asks for
     * deliberately once the original plan no longer reflects their intent (FC-06).
     */
    public function store(FinancialYear $year, CapturePlanBaseline $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        $action->handle($year);

        return back()->with('status', 'Baseline reset to your current plan.');
    }
}
