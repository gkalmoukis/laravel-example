<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\FinancialYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A calendar year the user has a plan for.
 *
 * Transactions are not linked to a year by key: a transaction belongs to whichever year
 * contains its date, so recording one before the year exists is allowed and starts
 * counting the moment the year is created.
 *
 * @property-read int $id
 * @property-read string $user_id
 * @property-read int $year
 * @property-read CarbonInterface|null $setup_completed_at
 * @property-read array<string, mixed>|null $baseline
 * @property-read CarbonInterface|null $baseline_captured_at
 * @property-read int|null $copied_from_id
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read SalaryModel|null $salaryModel
 * @property-read Collection<int, PlanItem> $planItems
 * @property-read Collection<int, NetWorthSnapshot> $netWorthSnapshots
 */
#[Fillable([
    'year',
    'copied_from_id',
])]
final class FinancialYear extends Model
{
    /** @use HasFactory<FinancialYearFactory> */
    use HasFactory;

    /**
     * The span a year may be created for: far enough back to record history, far enough
     * forward to plan ahead (YEAR-01).
     */
    public const int EARLIEST_YEAR = 2000;

    public const int YEARS_AHEAD = 5;

    public static function latestSelectableYear(): int
    {
        return (int) date('Y') + self::YEARS_AHEAD;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'year' => 'integer',
            'setup_completed_at' => 'datetime',
            'baseline' => 'array',
            'baseline_captured_at' => 'datetime',
            'copied_from_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<SalaryModel, $this>
     */
    public function salaryModel(): HasOne
    {
        return $this->hasOne(SalaryModel::class);
    }

    /**
     * @return HasMany<PlanItem, $this>
     */
    public function planItems(): HasMany
    {
        return $this->hasMany(PlanItem::class);
    }

    /**
     * @return HasMany<NetWorthSnapshot, $this>
     */
    public function netWorthSnapshots(): HasMany
    {
        return $this->hasMany(NetWorthSnapshot::class);
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }

    public function hasBaseline(): bool
    {
        return $this->baseline !== null;
    }
}
