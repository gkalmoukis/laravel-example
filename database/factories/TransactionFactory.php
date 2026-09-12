<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EntrySource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'occurred_on' => now()->toDateString(),
            'amount_cents' => 1_250,
            'category_id' => Category::factory(),
            'subcategory_id' => null,
            'account_id' => null,
            'description' => fake()->words(2, true),
            'notes' => null,
            'entry_source' => EntrySource::QuickAdd,
            'entry_duration_ms' => null,
        ];
    }

    public function income(): self
    {
        return $this->state(fn (array $attributes): array => ['type' => TransactionType::Income]);
    }

    public function on(string $date): self
    {
        return $this->state(fn (array $attributes): array => ['occurred_on' => $date]);
    }

    public function ofCents(int $cents): self
    {
        return $this->state(fn (array $attributes): array => ['amount_cents' => $cents]);
    }

    public function in(Category $category): self
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $category->user_id,
            'category_id' => $category->id,
            'type' => $category->type,
        ]);
    }

    public function paidFrom(Account $account): self
    {
        return $this->state(fn (array $attributes): array => ['account_id' => $account->id]);
    }
}
