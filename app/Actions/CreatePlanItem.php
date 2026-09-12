<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Adds a planned income or expense, with the twelve monthly amounts its frequency implies.
 *
 * The schedule is only a starting point: every month can be edited afterwards (YEAR-06).
 */
final readonly class CreatePlanItem
{
    public function __construct(
        private BuildPlanSchedule $schedule,
        private SyncPlanItemAmounts $amounts,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $customMonths
     */
    public function handle(FinancialYear $financialYear, array $attributes, Money $amount, array $customMonths = []): PlanItem
    {
        return DB::transaction(function () use ($financialYear, $attributes, $amount, $customMonths): PlanItem {
            $frequency = $this->enumFrom(Frequency::class, $attributes['frequency'] ?? null, Frequency::Monthly);
            $kind = $this->enumFrom(PlanItemKind::class, $attributes['kind'] ?? null, PlanItemKind::Recurring);
            $allocation = $this->enumFrom(Allocation::class, $attributes['allocation'] ?? null, Allocation::LumpSum);

            // Spreading only means something for an irregular cost; a recurring item is
            // already spread by definition.
            if ($kind !== PlanItemKind::Irregular) {
                $allocation = Allocation::LumpSum;
            }

            $startMonth = $this->monthFrom($attributes['start_month'] ?? null);

            $highestSortOrder = $financialYear->planItems()->max('sort_order');

            $planItem = $financialYear->planItems()->create([
                ...$attributes,
                'type' => $this->enumFrom(TransactionType::class, $attributes['type'] ?? null, TransactionType::Expense),
                'kind' => $kind,
                'frequency' => $frequency,
                'start_month' => $startMonth,
                'allocation' => $allocation,
                'source' => PlanItemSource::Manual,
                'sort_order' => is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0,
            ]);

            $this->amounts->handle(
                $planItem,
                $this->schedule->handle($frequency, $amount, $startMonth, $allocation, $customMonths),
            );

            return $planItem;
        });
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @param  T  $fallback
     * @return T
     */
    private function enumFrom(string $enum, mixed $value, mixed $fallback): mixed
    {
        if ($value instanceof $enum) {
            return $value;
        }

        return is_string($value) ? ($enum::tryFrom($value) ?? $fallback) : $fallback;
    }

    private function monthFrom(mixed $value): int
    {
        return is_numeric($value) && $value >= 1 && $value <= 12 ? (int) $value : 1;
    }
}
