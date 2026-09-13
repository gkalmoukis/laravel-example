<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CopyFinancialYear;
use App\Actions\CreateFinancialYear;
use App\Http\Requests\StoreFinancialYearRequest;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class FinancialYearController
{
    public function create(#[CurrentUser] User $user): Response
    {
        $existing = $user->financialYears()->orderByDesc('year')->get();

        return Inertia::render('years/create', [
            'suggestedYear' => $this->suggestedYear($user),
            'earliestYear' => FinancialYear::EARLIEST_YEAR,
            'latestYear' => FinancialYear::latestSelectableYear(),
            // Copying is only offered when there is something to copy (YEAR-02).
            'copyableYears' => $existing
                ->map(fn (FinancialYear $year): array => ['id' => $year->id, 'year' => $year->year])
                ->all(),
            'takenYears' => $existing->pluck('year')->all(),
        ]);
    }

    public function store(
        StoreFinancialYearRequest $request,
        #[CurrentUser] User $user,
        CreateFinancialYear $create,
        CopyFinancialYear $copy,
    ): RedirectResponse {
        $year = $request->integer('year');
        $source = $this->copySource($user, $request->integer('copy_from_id'));

        $financialYear = $source instanceof FinancialYear
            ? $copy->handle($user, $source, $year)
            : $create->handle($user, $year);

        return to_route('year-setup.show', [
            'year' => $financialYear->year,
            'step' => 'opening',
        ]);
    }

    private function copySource(User $user, int $copyFromId): ?FinancialYear
    {
        if ($copyFromId === 0) {
            return null;
        }

        return $user->financialYears()->whereKey($copyFromId)->first();
    }

    /**
     * The current calendar year unless it already has a plan, in which case the next one
     * that does not.
     */
    private function suggestedYear(User $user): int
    {
        $taken = $user->financialYears()->pluck('year')->all();

        $year = (int) date('Y');

        while (in_array($year, $taken, true) && $year <= FinancialYear::latestSelectableYear()) {
            $year++;
        }

        return $year;
    }
}
