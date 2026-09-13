<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\SalaryModel;

/**
 * The salary arrangement, or nothing when the year has none (INC-02).
 *
 * Shared by the setup wizard and the plan's income tab, which both offer to change it.
 */
final readonly class PresentSalaryModel
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(FinancialYear $year): ?array
    {
        $salaryModel = $year->salaryModel()->first();

        if (! $salaryModel instanceof SalaryModel) {
            return null;
        }

        return [
            'name' => $salaryModel->name,
            'baseAmountCents' => $salaryModel->base_amount_cents->cents,
            'payments' => $salaryModel->payments,
        ];
    }
}
