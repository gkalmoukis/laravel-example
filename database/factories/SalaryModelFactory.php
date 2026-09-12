<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialYear;
use App\Models\SalaryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryModel>
 */
final class SalaryModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_year_id' => FinancialYear::factory(),
            'name' => 'Salary',
            'base_amount_cents' => 180_000,
            'payments' => SalaryModel::defaultPayments(),
        ];
    }

    /**
     * Twelve payments a year: the bonuses exist but are switched off (INC-05).
     */
    public function twelvePayments(): self
    {
        return $this->state(fn (array $attributes): array => [
            'payments' => SalaryModel::defaultPayments(enabled: false),
        ]);
    }
}
