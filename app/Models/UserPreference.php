<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $currency
 * @property-read string $format_locale
 * @property-read string $timezone
 * @property-read int $salary_payments
 * @property-read int|null $default_account_id
 * @property-read int $emergency_fund_months
 * @property-read int $budget_warning_threshold_percent
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Account|null $defaultAccount
 */
#[Fillable([
    'format_locale',
    'timezone',
    'salary_payments',
    'default_account_id',
    'emergency_fund_months',
    'budget_warning_threshold_percent',
])]
final class UserPreference extends Model
{
    /** @use HasFactory<UserPreferenceFactory> */
    use HasFactory;

    /**
     * The locales offered in settings (PREF-01).
     *
     * @var list<string>
     */
    public const array FORMAT_LOCALES = ['el-GR', 'en-GB', 'en-US'];

    /**
     * @var list<int>
     */
    public const array SALARY_PAYMENTS = [12, 14];

    public const string DEFAULT_CURRENCY = 'EUR';

    public const string DEFAULT_FORMAT_LOCALE = 'el-GR';

    public const string DEFAULT_TIMEZONE = 'Europe/Athens';

    /**
     * How far over plan a category may go before it is called a warning rather than fine
     * (§7.4). The user can change it; this is what they start with.
     */
    public const int DEFAULT_WARNING_THRESHOLD = 10;

    /**
     * Mirrors the column defaults, so a row created without explicit values carries them
     * in memory too rather than only after a reload.
     *
     * @var array<string, string|int>
     */
    protected $attributes = [
        'currency' => self::DEFAULT_CURRENCY,
        'format_locale' => self::DEFAULT_FORMAT_LOCALE,
        'timezone' => self::DEFAULT_TIMEZONE,
        'salary_payments' => 14,
        'emergency_fund_months' => 6,
        'budget_warning_threshold_percent' => self::DEFAULT_WARNING_THRESHOLD,
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'currency' => 'string',
            'format_locale' => 'string',
            'timezone' => 'string',
            'salary_payments' => 'integer',
            'default_account_id' => 'integer',
            'emergency_fund_months' => 'integer',
            'budget_warning_threshold_percent' => 'integer',
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
     * @return BelongsTo<Account, $this>
     */
    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }
}
