<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'type' => TransactionType::Expense,
            'name' => fake()->unique()->words(2, true),
            'system_key' => null,
            'is_active' => true,
            'is_essential' => false,
            'is_irregular' => false,
            'sort_order' => 0,
        ];
    }

    public function income(): self
    {
        return $this->state(fn (array $attributes): array => ['type' => TransactionType::Income]);
    }

    public function expense(): self
    {
        return $this->state(fn (array $attributes): array => ['type' => TransactionType::Expense]);
    }

    /**
     * A subcategory inherits its parent's owner and type, which is the only shape the
     * application ever allows (CAT-02).
     */
    public function childOf(Category $parent): self
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $parent->user_id,
            'parent_id' => $parent->id,
            'type' => $parent->type,
        ]);
    }

    public function system(string $key): self
    {
        return $this->state(fn (array $attributes): array => ['system_key' => $key]);
    }

    public function essential(): self
    {
        return $this->state(fn (array $attributes): array => ['is_essential' => true]);
    }

    public function irregular(): self
    {
        return $this->state(fn (array $attributes): array => ['is_irregular' => true]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
