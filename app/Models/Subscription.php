<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Frequency;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something charged on a schedule: a streaming service, an insurance premium, a gym.
 *
 * Only one date is stored — any single charge, past or future. Every other billing date
 * is worked out from it, so nothing has to be kept up to date and a "next billing date"
 * can never be left showing a day that has already gone (SUB-03).
 *
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $name
 * @property-read Money $amount_cents
 * @property-read Frequency $frequency
 * @property-read CarbonInterface $billing_anchor_date
 * @property-read int $category_id
 * @property-read int|null $subcategory_id
 * @property-read int|null $account_id
 * @property-read bool $is_active
 * @property-read CarbonInterface|null $deactivated_on
 * @property-read string|null $notes
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Category $category
 * @property-read Category|null $subcategory
 * @property-read Account|null $account
 */
#[Fillable([
    'name',
    'amount_cents',
    'frequency',
    'billing_anchor_date',
    'category_id',
    'subcategory_id',
    'account_id',
    'is_active',
    'deactivated_on',
    'notes',
])]
final class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    private const int MONTHS = 12;

    /**
     * How many months apart the charges are.
     *
     * A subscription is billed on a whole number of months, which is what lets every
     * date be derived from one anchor. Anything finer would need a stored schedule.
     *
     * @return array<string, int>
     */
    public static function intervals(): array
    {
        return [
            Frequency::Monthly->value => 1,
            Frequency::Quarterly->value => 3,
            Frequency::SemiAnnual->value => 6,
            Frequency::Annual->value => 12,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'amount_cents' => MoneyCast::class,
            'frequency' => Frequency::class,
            'billing_anchor_date' => 'date',
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'account_id' => 'integer',
            'is_active' => 'boolean',
            'deactivated_on' => 'date',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function intervalMonths(): int
    {
        return self::intervals()[$this->frequency->value] ?? 1;
    }

    /**
     * The first charge falling on or after the given day (SUB-03).
     *
     * Walks the sequence from the anchor rather than adding months repeatedly to a moving
     * date: adding a month to 31 January gives 28 February, and adding another gives 28
     * March rather than the 31st. Counting from the anchor each time keeps the day of the
     * month wherever the month is long enough for it (EDGE-05).
     */
    public function nextBillingDateFrom(CarbonInterface $from): CarbonImmutable
    {
        $anchor = CarbonImmutable::parse($this->billing_anchor_date->toDateString());
        $day = CarbonImmutable::parse($from->toDateString());
        $interval = $this->intervalMonths();

        // How many whole intervals separate the anchor from the day asked about, floored
        // so the candidate starts at or before it.
        $monthsApart = ($day->year - $anchor->year) * self::MONTHS + ($day->month - $anchor->month);
        $steps = intdiv($monthsApart, $interval);

        if ($monthsApart < 0 && $monthsApart % $interval !== 0) {
            $steps--;
        }

        $candidate = $this->chargeAt($anchor, $steps * $interval);

        return $candidate->lessThan($day)
            ? $this->chargeAt($anchor, ($steps + 1) * $interval)
            : $candidate;
    }

    /**
     * Which months of a year this is charged in (SUB-04).
     *
     * Months after it was stopped are left out: the plan should show what was actually
     * paid, not what would have been.
     *
     * @return list<int>
     */
    public function billingMonthsIn(int $year): array
    {
        $anchor = CarbonImmutable::parse($this->billing_anchor_date->toDateString());
        $interval = $this->intervalMonths();

        // Start from the first charge of the year, whichever direction the anchor lies in.
        $monthsApart = ($year - $anchor->year) * self::MONTHS - ($anchor->month - 1);
        $steps = (int) ceil($monthsApart / $interval);

        $months = [];

        // Rounding up means the first candidate is never earlier than January of the
        // year asked about, so the walk only ever has to stop, never skip.
        for ($step = $steps; ; $step++) {
            $charge = $this->chargeAt($anchor, $step * $interval);

            if ($charge->year > $year) {
                break;
            }

            if ($this->wasStoppedBefore($charge)) {
                break;
            }

            $months[] = $charge->month;
        }

        return $months;
    }

    /**
     * What this costs a month, for comparing subscriptions of different frequencies
     * (SUB-01).
     *
     * Integer division: a figure that claims a precision the schedule does not have would
     * be worse than one that is plainly rounded.
     */
    public function monthlyEquivalentCents(): int
    {
        return intdiv($this->amount_cents->cents, $this->intervalMonths());
    }

    public function annualCents(): int
    {
        return intdiv(self::MONTHS, $this->intervalMonths()) * $this->amount_cents->cents;
    }

    /**
     * The charge that many months after the anchor, clamped to the month's last day.
     *
     * A subscription anchored on the 31st is charged on the 30th in a thirty-day month
     * and on the 28th in February — never rolled into the next month (EDGE-05).
     */
    private function chargeAt(CarbonImmutable $anchor, int $monthsAfter): CarbonImmutable
    {
        $month = $anchor->startOfMonth()->addMonths($monthsAfter);

        return $month->setDay(min($anchor->day, $month->daysInMonth));
    }

    private function wasStoppedBefore(CarbonImmutable $charge): bool
    {
        $stopped = $this->deactivated_on;

        return $stopped !== null && $charge->greaterThan(CarbonImmutable::parse($stopped->toDateString()));
    }
}
