<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteMonth;
use App\Actions\ReopenMonth;
use App\Http\Requests\StoreMonthCompletionRequest;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Finishing a month, and changing your mind about it (MON-04, MON-05).
 */
final readonly class MonthCompletionController
{
    private const int MONTHS = 12;

    public function store(
        StoreMonthCompletionRequest $request,
        FinancialYear $year,
        int $month,
        CompleteMonth $action,
    ): RedirectResponse {
        Gate::authorize('update', $year);

        abort_unless($month >= 1 && $month <= self::MONTHS, 404);

        $action->handle($year, $month, $year->user->today(), $request->isConfirmed());

        return back()->with('status', 'Month marked as complete.');
    }

    public function destroy(FinancialYear $year, int $month, ReopenMonth $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        abort_unless($month >= 1 && $month <= self::MONTHS, 404);

        $action->handle($year, $month);

        return back()->with('status', 'Month reopened. Your forecast has been updated.');
    }
}
