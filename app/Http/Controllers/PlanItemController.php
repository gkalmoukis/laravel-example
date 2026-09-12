<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePlanItem;
use App\Actions\DeletePlanItem;
use App\Actions\UpdatePlanItem;
use App\Http\Requests\StorePlanItemRequest;
use App\Http\Requests\UpdatePlanItemRequest;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class PlanItemController
{
    public function store(StorePlanItemRequest $request, FinancialYear $year, CreatePlanItem $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        $action->handle($year, $request->planItemAttributes(), $request->amount(), $request->customMonths());

        return back()->with('status', 'Added to your plan.');
    }

    public function update(UpdatePlanItemRequest $request, FinancialYear $year, PlanItem $planItem, UpdatePlanItem $action): RedirectResponse
    {
        Gate::authorize('update', $planItem);

        $action->handle($planItem, $request->planItemAttributes(), $request->amount(), $request->customMonths());

        return back()->with('status', 'Plan updated.');
    }

    public function destroy(FinancialYear $year, PlanItem $planItem, DeletePlanItem $action): RedirectResponse
    {
        Gate::authorize('delete', $planItem);

        $action->handle($planItem);

        return back()->with('status', 'Removed from your plan.');
    }
}
