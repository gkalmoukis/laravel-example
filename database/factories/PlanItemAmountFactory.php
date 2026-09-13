<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlanItem;
use App\Models\PlanItemAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanItemAmount>
 */
final class PlanItemAmountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_item_id' => PlanItem::factory(),
            'month' => 1,
            'amount_cents' => 10_000,
        ];
    }
}
