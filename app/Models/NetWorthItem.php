<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NetWorthItemKind;
use Carbon\CarbonInterface;
use Database\Factories\NetWorthItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the user owns or owes: a bank balance, an investment, a debt.
 *
 * Distinct from an account, which only labels where a transaction happened. Holdings
 * carry value; accounts do not.
 *
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $name
 * @property-read NetWorthItemKind $kind
 * @property-read bool $is_active
 * @property-read int $sort_order
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Collection<int, NetWorthSnapshot> $snapshots
 */
#[Fillable([
    'name',
    'kind',
    'is_active',
    'sort_order',
])]
final class NetWorthItem extends Model
{
    /** @use HasFactory<NetWorthItemFactory> */
    use HasFactory;

    /**
     * The groups the opening position starts with, so the fastest path through setup is
     * typing one or two numbers (OPEN-02).
     *
     * @return list<array{name: string, kind: NetWorthItemKind}>
     */
    public static function defaultItems(): array
    {
        return [
            ['name' => 'Cash & bank', 'kind' => NetWorthItemKind::Cash],
            ['name' => 'Emergency fund', 'kind' => NetWorthItemKind::EmergencyFund],
            ['name' => 'Investments', 'kind' => NetWorthItemKind::Investment],
            ['name' => 'Other assets', 'kind' => NetWorthItemKind::OtherAsset],
            ['name' => 'Debts', 'kind' => NetWorthItemKind::Debt],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'name' => 'string',
            'kind' => NetWorthItemKind::class,
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

    /**
     * @return HasMany<NetWorthSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(NetWorthSnapshot::class);
    }

    /**
     * Money that can be spent: what transactions move in and out. The emergency fund
     * counts, so the app can tell when a forecast would eat into it.
     */
    public function isLiquid(): bool
    {
        return in_array($this->kind, [NetWorthItemKind::Cash, NetWorthItemKind::EmergencyFund], true);
    }
}
