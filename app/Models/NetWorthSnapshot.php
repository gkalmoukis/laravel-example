<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\NetWorthSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a holding was worth at the end of a month. Month zero is the opening position:
 * where the year starts, before January.
 *
 * Debts are stored as a positive outstanding balance and subtracted when net worth is
 * computed, so no stored amount is ever negative.
 *
 * @property-read int $id
 * @property-read int $net_worth_item_id
 * @property-read int $financial_year_id
 * @property-read int $month
 * @property-read Money $value_cents
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read NetWorthItem $netWorthItem
 * @property-read FinancialYear $financialYear
 */
// The two keys identify which holding, in which year, this value belongs to. They are
// structural rather than user input: the action that writes snapshots checks the holding
// belongs to the user before touching anything.
#[Fillable([
    'net_worth_item_id',
    'financial_year_id',
    'month',
    'value_cents',
])]
final class NetWorthSnapshot extends Model
{
    /** @use HasFactory<NetWorthSnapshotFactory> */
    use HasFactory;

    /**
     * The opening position, recorded before the year begins.
     */
    public const int OPENING_MONTH = 0;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'net_worth_item_id' => 'integer',
            'financial_year_id' => 'integer',
            'month' => 'integer',
            'value_cents' => MoneyCast::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NetWorthItem, $this>
     */
    public function netWorthItem(): BelongsTo
    {
        return $this->belongsTo(NetWorthItem::class);
    }

    /**
     * @return BelongsTo<FinancialYear, $this>
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
