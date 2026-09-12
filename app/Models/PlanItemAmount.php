<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\PlanItemAmountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a plan item expects in one month. Every item has twelve of these, zero where
 * nothing is planned, so no month has to be inferred from absence.
 *
 * @property-read int $id
 * @property-read int $plan_item_id
 * @property-read int $month
 * @property-read Money $amount_cents
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read PlanItem $planItem
 */
#[Fillable([
    'month',
    'amount_cents',
])]
final class PlanItemAmount extends Model
{
    /** @use HasFactory<PlanItemAmountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'plan_item_id' => 'integer',
            'month' => 'integer',
            'amount_cents' => MoneyCast::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlanItem, $this>
     */
    public function planItem(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class);
    }
}
