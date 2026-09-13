<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteYearSetup;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class YearSetupCompletionController
{
    public function store(FinancialYear $year, CompleteYearSetup $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        $action->handle($year);

        return to_route('plan.show', ['year' => $year->year, 'tab' => 'income'])
            ->with('status', 'Your plan is set up.');
    }
}
