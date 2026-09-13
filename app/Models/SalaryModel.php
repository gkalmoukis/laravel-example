<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\SalaryModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Greek 14-payment salary arrangement: a monthly salary plus a Christmas bonus, an
 * Easter bonus and a vacation allowance.
 *
 * It is a default rather than a rule. Any payment can be disabled, moved, or given a
 * fixed amount instead of a multiple, and the whole model can be removed in favour of
 * planning salary by hand (INC-07).
 *
 * @property-read int $id
 * @property-read int $financial_year_id
 * @property-read string $name
 * @property-read Money $base_amount_cents
 * @property-read array<string, mixed> $payments
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read FinancialYear $financialYear
 * @property-read Collection<int, PlanItem> $planItems
 */
#[Fillable([
    'name',
    'base_amount_cents',
    'payments',
])]
final class SalaryModel extends Model
{
    /** @use HasFactory<SalaryModelFactory> */
    use HasFactory;

    public const string CHRISTMAS_BONUS = 'christmas_bonus';

    public const string EASTER_BONUS = 'easter_bonus';

    public const string VACATION_ALLOWANCE = 'vacation_allowance';

    public const string MODE_MULTIPLIER = 'Multiplier';

    public const string MODE_FIXED_AMOUNT = 'FixedAmount';

    /**
     * The customary Greek arrangement: a full extra salary at Christmas, half at Easter
     * and half before the summer.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaultPayments(bool $enabled = true): array
    {
        return [
            self::CHRISTMAS_BONUS => [
                'enabled' => $enabled,
                'month' => 12,
                'mode' => self::MODE_MULTIPLIER,
                'multiplier' => '1.0',
                'amount_cents' => 0,
            ],
            self::EASTER_BONUS => [
                'enabled' => $enabled,
                'month' => 4,
                'mode' => self::MODE_MULTIPLIER,
                'multiplier' => '0.5',
                'amount_cents' => 0,
            ],
            self::VACATION_ALLOWANCE => [
                'enabled' => $enabled,
                'month' => 6,
                'mode' => self::MODE_MULTIPLIER,
                'multiplier' => '0.5',
                'amount_cents' => 0,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'financial_year_id' => 'integer',
            'name' => 'string',
            'base_amount_cents' => MoneyCast::class,
            'payments' => 'array',
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

    /**
     * @return HasMany<PlanItem, $this>
     */
    public function planItems(): HasMany
    {
        return $this->hasMany(PlanItem::class);
    }
}
