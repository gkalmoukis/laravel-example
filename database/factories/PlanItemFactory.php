<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanItem>
 */
final class PlanItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_year_id' => FinancialYear::factory(),
            'type' => TransactionType::Expense,
            'kind' => PlanItemKind::Recurring,
            'category_id' => Category::factory(),
            'subcategory_id' => null,
            'name' => fake()->unique()->words(2, true),
            'frequency' => Frequency::Monthly,
            'start_month' => 1,
            'payment_day' => null,
            'is_fixed' => true,
            'allocation' => Allocation::LumpSum,
            'source' => PlanItemSource::Manual,
            'salary_model_id' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function income(): self
    {
        return $this->state(fn (array $attributes): array => ['type' => TransactionType::Income]);
    }

    public function irregular(): self
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => PlanItemKind::Irregular,
            'frequency' => Frequency::Annual,
        ]);
    }

    /**
     * An irregular cost set aside monthly rather than paid in one month.
     */
    public function spread(): self
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => PlanItemKind::Irregular,
            'frequency' => Frequency::Annual,
            'allocation' => Allocation::Spread,
        ]);
    }

    public function fromSalaryModel(int $salaryModelId): self
    {
        return $this->state(fn (array $attributes): array => [
            'source' => PlanItemSource::SalaryModel,
            'salary_model_id' => $salaryModelId,
            'type' => TransactionType::Income,
        ]);
    }
}
