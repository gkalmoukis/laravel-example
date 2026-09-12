<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MonthClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that a month has been finished.
 *
 * Only completion is stored: everything else about a month's status is derived from
 * whether anything was recorded in it (MON-01).
 *
 * @property-read int $id
 * @property-read int $financial_year_id
 * @property-read int $month
 * @property-read CarbonInterface|null $completed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read FinancialYear $financialYear
 */
#[Fillable([
    'financial_year_id',
    'month',
    'completed_at',
])]
final class MonthClosure extends Model
{
    /** @use HasFactory<MonthClosureFactory> */
    use HasFactory;

    /**
     * Whether a given month of a given user's year has been finished.
     *
     * A finished month is treated as final, so anything dated inside it is refused until
     * the user reopens it (TXV-02).
     */
    public static function isMonthComplete(string $userId, int $year, int $month): bool
    {
        return self::query()
            ->whereNotNull('completed_at')
            ->where('month', $month)
            ->whereHas('financialYear', fn (Builder $query): Builder => $query
                ->where('user_id', $userId)
                ->where('year', $year))
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'financial_year_id' => 'integer',
            'month' => 'integer',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FinancialYear, $this>
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
