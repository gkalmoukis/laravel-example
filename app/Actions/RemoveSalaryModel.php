<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;

/**
 * Removes the salary arrangement and everything it generated, leaving the user to plan
 * salary by hand (INC-07).
 *
 * Income the user planned themselves is untouched.
 */
final readonly class RemoveSalaryModel
{
    public function handle(FinancialYear $financialYear): void
    {
        DB::transaction(function () use ($financialYear): void {
            $salaryModel = $financialYear->salaryModel()->first();

            if ($salaryModel === null) {
                return;
            }

            $salaryModel->planItems()->delete();
            $salaryModel->delete();
        });
    }
}
