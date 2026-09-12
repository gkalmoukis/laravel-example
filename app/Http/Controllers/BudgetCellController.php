<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateBudgetCell;
use App\Http\Requests\UpdateBudgetCellRequest;
use App\Models\Category;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final readonly class BudgetCellController
{
    public function update(UpdateBudgetCellRequest $request, FinancialYear $year, UpdateBudgetCell $action): RedirectResponse
    {
        Gate::authorize('update', $year);

        $category = Category::query()
            ->where('user_id', $year->user_id)
            ->findOrFail($request->integer('category_id'));

        try {
            $action->handle($year, $category, $request->integer('month'), $request->amount());
        } catch (RuntimeException $runtimeException) {
            // The category has several planned items, so the grid cannot tell which one
            // the number belongs to (BUD-03).
            return back()->withErrors(['amount' => $runtimeException->getMessage()]);
        }

        return back()->with('status', 'Budget updated.');
    }
}
