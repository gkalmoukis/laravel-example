<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Frequency;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'amount_cents' => 1_299,
            'frequency' => Frequency::Monthly,
            'billing_anchor_date' => '2027-01-15',
            'category_id' => Category::factory(),
            'subcategory_id' => null,
            'account_id' => null,
            'is_active' => true,
            'deactivated_on' => null,
            'notes' => null,
        ];
    }

    public function monthly(): self
    {
        return $this->state(fn (array $attributes): array => ['frequency' => Frequency::Monthly]);
    }

    public function annual(): self
    {
        return $this->state(fn (array $attributes): array => ['frequency' => Frequency::Annual]);
    }

    public function quarterly(): self
    {
        return $this->state(fn (array $attributes): array => ['frequency' => Frequency::Quarterly]);
    }

    /**
     * Stopped, and stopped on a day: months after it are no longer charged (SUB-04).
     */
    public function inactive(string $deactivatedOn = '2027-06-30'): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'deactivated_on' => $deactivatedOn,
        ]);
    }

    public function anchoredOn(string $date): self
    {
        return $this->state(fn (array $attributes): array => ['billing_anchor_date' => $date]);
    }

    public function ofCents(int $cents): self
    {
        return $this->state(fn (array $attributes): array => ['amount_cents' => $cents]);
    }
}
