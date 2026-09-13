<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialYear>
 */
final class FinancialYearFactory extends Factory
{
    public function setUp(): self
    {
        return $this->state(fn (array $attributes): array => [
            'setup_completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => 2027,
            'setup_completed_at' => null,
            'baseline' => null,
            'baseline_captured_at' => null,
            'copied_from_id' => null,
        ];
    }

    public function forYear(int $year): self
    {
        return $this->state(fn (array $attributes): array => ['year' => $year]);
    }
}
