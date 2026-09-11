<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

/**
 * @property-read string $id
 * @property-read string $email
 * @property-read string $token_hash
 * @property-read bool $is_admin
 * @property-read string|null $invited_by
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface|null $accepted_at
 * @property-read CarbonInterface|null $revoked_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User|null $inviter
 */
#[Fillable([
    'email',
    'token_hash',
    'is_admin',
    'invited_by',
    'expires_at',
])]
final class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use Prunable;

    /**
     * Invitations that can still be accepted.
     *
     * Deliberately not an Eloquent scope: Rector forces scope methods to be protected,
     * which the strict architecture preset forbids on a final class.
     *
     * @return Builder<self>
     */
    public static function pending(): Builder
    {
        return self::query()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'email' => 'string',
            'token_hash' => 'string',
            'is_admin' => 'boolean',
            'invited_by' => 'string',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Notifications go to the invited address, which has no account yet (INV-05).
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * An invitation is pending while it has not been accepted, has not been revoked, and
     * has not expired. Every other state renders the same neutral error page (INV-07).
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Finished invitations are kept for a retention period, then removed (INV-11).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(config()->integer('invitations.prune_after_days'));

        return $this->newQuery()
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('accepted_at')
                ->orWhereNotNull('revoked_at')
                ->orWhere('expires_at', '<', now()))
            ->where('updated_at', '<', $cutoff);
    }
}
