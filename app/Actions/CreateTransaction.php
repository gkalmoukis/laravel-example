<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records something that actually happened (TXQ, TXF-02).
 *
 * Nothing is derived here. A transaction is the only place a real amount is entered, and
 * every actual, variance, balance and forecast is read back out of these rows (UX-02).
 */
final readonly class CreateTransaction
{
    public function __construct(private AllowChangeInMonth $allowChange) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, Money $amount, CarbonImmutable $occurredOn, bool $reopenMonth = false): Transaction
    {
        return DB::transaction(function () use ($user, $attributes, $amount, $occurredOn, $reopenMonth): Transaction {
            $this->allowChange->handle($user, $occurredOn, $reopenMonth);

            return $user->transactions()->create([
                ...$attributes,
                'occurred_on' => $occurredOn,
                'amount_cents' => $amount,
            ]);
        });
    }
}
