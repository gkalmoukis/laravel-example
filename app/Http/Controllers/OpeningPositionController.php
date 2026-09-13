<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateOpeningPosition;
use App\Http\Requests\UpdateOpeningPositionRequest;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class OpeningPositionController
{
    public function update(UpdateOpeningPositionRequest $request, FinancialYear $year, UpdateOpeningPosition $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        // Amounts are parsed server-side, so the formats a user may type work everywhere.
        $action->handle($year, $request->values());

        return back()->with('status', 'Opening position saved.');
    }
}
