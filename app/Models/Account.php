<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Carbon\CarbonInterface;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $name
 * @property-read AccountType $type
 * @property-read bool $is_active
 * @property-read int $sort_order
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
#[Fillable([
    'name',
    'type',
    'is_active',
    'sort_order',
])]
final class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * Accounts offered in pickers. Inactive accounts stay visible on the records that
     * already reference them (ACC-01).
     *
     * @return Builder<self>
     */
    public static function active(): Builder
    {
        return self::query()->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'name' => 'string',
            'type' => AccountType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
}
