<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NetWorthItemKind;
use App\Models\NetWorthItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetWorthItem>
 */
final class NetWorthItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'kind' => NetWorthItemKind::Cash,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function ofKind(NetWorthItemKind $kind): self
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
