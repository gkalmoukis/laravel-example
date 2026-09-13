<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;

/**
 * Marks the setup wizard finished and freezes the plan as the baseline (YEAR-05, FC-06).
 *
 * Nothing is locked by this: every planned amount stays editable afterwards. What it
 * fixes is the point of comparison, so later drift is measured against what was
 * originally intended.
 */
final readonly class CompleteYearSetup
{
    public function __construct(private CapturePlanBaseline $baseline) {}

    public function handle(FinancialYear $financialYear): FinancialYear
    {
        return DB::transaction(function () use ($financialYear): FinancialYear {
            $financialYear->forceFill(['setup_completed_at' => now()])->save();

            return $this->baseline->handle($financialYear);
        });
    }
}
