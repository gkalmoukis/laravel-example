<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\GoalType;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $user_id
 * @property-read GoalType $type
 * @property-read string $name
 * @property-read Money|null $target_amount_cents
 * @property-read bool $target_is_custom
 * @property-read Money $current_amount_cents
 * @property-read Money|null $monthly_contribution_cents
 * @property-read CarbonInterface|null $target_date
 * @property-read CarbonInterface|null $archived_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
#[Fillable([
    'type',
    'name',
    'target_amount_cents',
    'target_is_custom',
    'current_amount_cents',
    'monthly_contribution_cents',
    'target_date',
])]
final class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'type' => GoalType::class,
            'name' => 'string',
            'target_amount_cents' => MoneyCast::class,
            'target_is_custom' => 'boolean',
            'current_amount_cents' => MoneyCast::class,
            'monthly_contribution_cents' => MoneyCast::class,
            'target_date' => 'date',
            'archived_at' => 'datetime',
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
