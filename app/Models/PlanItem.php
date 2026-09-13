<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One planned income or expense within a year, with its twelve monthly amounts.
 *
 * @property-read int $id
 * @property-read int $financial_year_id
 * @property-read TransactionType $type
 * @property-read PlanItemKind $kind
 * @property-read int $category_id
 * @property-read int|null $subcategory_id
 * @property-read string $name
 * @property-read Frequency $frequency
 * @property-read int $start_month
 * @property-read int|null $payment_day
 * @property-read bool $is_fixed
 * @property-read Allocation $allocation
 * @property-read PlanItemSource $source
 * @property-read int|null $salary_model_id
 * @property-read int|null $subscription_id
 * @property-read string|null $notes
 * @property-read int $sort_order
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read FinancialYear $financialYear
 * @property-read Category $category
 * @property-read Category|null $subcategory
 * @property-read SalaryModel|null $salaryModel
 * @property-read Subscription|null $subscription
 * @property-read Collection<int, PlanItemAmount> $amounts
 */
#[Fillable([
    'type',
    'kind',
    'category_id',
    'subcategory_id',
    'name',
    'frequency',
    'start_month',
    'payment_day',
    'is_fixed',
    'allocation',
    'source',
    'salary_model_id',
    'subscription_id',
    'notes',
    'sort_order',
])]
final class PlanItem extends Model
{
    /** @use HasFactory<PlanItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'financial_year_id' => 'integer',
            'type' => TransactionType::class,
            'kind' => PlanItemKind::class,
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'name' => 'string',
            'frequency' => Frequency::class,
            'start_month' => 'integer',
            'payment_day' => 'integer',
            'is_fixed' => 'boolean',
            'allocation' => Allocation::class,
            'source' => PlanItemSource::class,
            'salary_model_id' => 'integer',
            'subscription_id' => 'integer',
            'notes' => 'string',
            'sort_order' => 'integer',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * The subscription this item was generated from, if it was (SUB-04).
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<SalaryModel, $this>
     */
    public function salaryModel(): BelongsTo
    {
        return $this->belongsTo(SalaryModel::class);
    }

    /**
     * @return HasMany<PlanItemAmount, $this>
     */
    public function amounts(): HasMany
    {
        return $this->hasMany(PlanItemAmount::class);
    }

    /**
     * Whether the user created this item, as opposed to it being generated from the
     * salary model or a subscription. Generated items are rebuilt from their source;
     * manual ones are never overwritten.
     */
    public function isManual(): bool
    {
        return $this->source === PlanItemSource::Manual;
    }

    /**
     * An irregular cost the user sets aside for monthly rather than paying in one month
     * (IRR-02). These are compared year-to-date rather than month by month, because the
     * real payment lands in a single month.
     */
    public function isSpread(): bool
    {
        return $this->allocation === Allocation::Spread;
    }

    /**
     * The day this is actually due in a given month (EDGE-05).
     *
     * A payment day of 31 is a way of saying "the end of the month", so a month that has
     * no 31st resolves to its last day rather than rolling into the next one — which
     * would move the cost into a month that never planned for it. February settles on the
     * 28th, or the 29th in a leap year.
     */
    public function paymentDateIn(int $month): ?CarbonImmutable
    {
        $day = $this->payment_day;

        if ($day === null) {
            return null;
        }

        $first = CarbonImmutable::parse(sprintf('%d-%02d-01', $this->financialYear->year, $month));

        return $first->setDay(min($day, $first->daysInMonth));
    }
}
