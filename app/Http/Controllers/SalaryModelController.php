<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RemoveSalaryModel;
use App\Actions\SaveSalaryModel;
use App\Http\Requests\UpdateSalaryModelRequest;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class SalaryModelController
{
    public function update(UpdateSalaryModelRequest $request, FinancialYear $year, SaveSalaryModel $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        $action->handle($year, $request->baseAmount(), $request->payments(), $request->salaryName());

        return back()->with('status', 'Salary saved.');
    }

    public function destroy(FinancialYear $year, RemoveSalaryModel $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        $action->handle($year);

        return back()->with('status', 'Salary removed. You can plan it by hand instead.');
    }
}
