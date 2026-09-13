<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveNetWorthSnapshots;
use App\Http\Requests\UpdateNetWorthSnapshotRequest;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class NetWorthSnapshotController
{
    private const int MONTHS = 12;

    public function update(
        UpdateNetWorthSnapshotRequest $request,
        FinancialYear $year,
        int $month,
        SaveNetWorthSnapshots $action,
    ): RedirectResponse {
        Gate::authorize('update', $year);

        abort_unless($month >= 1 && $month <= self::MONTHS, 404);

        $action->handle($year, $month, $request->values());

        return back()->with('status', 'What you have has been recorded.');
    }
}
