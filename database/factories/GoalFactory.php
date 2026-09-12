<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
final class GoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => GoalType::Other,
            'name' => fake()->unique()->words(2, true),
            'target_amount_cents' => 500_000,
            'target_is_custom' => false,
            'current_amount_cents' => 0,
            'monthly_contribution_cents' => null,
            'target_date' => null,
            'archived_at' => null,
        ];
    }

    /**
     * The emergency fund goal every account gets at provisioning. Its target is computed
     * from essential expenses unless the user overrides it, so no amount is stored.
     */
    public function emergencyFund(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => GoalType::EmergencyFund,
            'name' => 'Emergency Fund',
            'target_amount_cents' => null,
            'target_is_custom' => false,
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes): array => ['archived_at' => now()]);
    }
}
