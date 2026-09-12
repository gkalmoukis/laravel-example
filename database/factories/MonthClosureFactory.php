<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialYear;
use App\Models\MonthClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthClosure>
 */
final class MonthClosureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_year_id' => FinancialYear::factory(),
            'month' => 1,
            'completed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes): array => ['completed_at' => now()]);
    }

    public function forMonth(int $month): self
    {
        return $this->state(fn (array $attributes): array => ['month' => $month]);
    }
}
