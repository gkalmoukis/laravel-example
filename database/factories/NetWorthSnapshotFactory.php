<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetWorthSnapshot>
 */
final class NetWorthSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'net_worth_item_id' => NetWorthItem::factory(),
            'financial_year_id' => FinancialYear::factory(),
            'month' => NetWorthSnapshot::OPENING_MONTH,
            'value_cents' => 100_000,
        ];
    }

    public function forMonth(int $month): self
    {
        return $this->state(fn (array $attributes): array => ['month' => $month]);
    }
}
