<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Transaction;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a transaction (TXF-01).
 *
 * Both months are checked, not just the new one: moving a transaction out of a finished
 * month changes that month's totals just as much as moving one into it.
 */
final readonly class UpdateTransaction
{
    public function __construct(private AllowChangeInMonth $allowChange) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Transaction $transaction, array $attributes, Money $amount, CarbonImmutable $occurredOn, bool $reopenMonth = false): Transaction
    {
        return DB::transaction(function () use ($transaction, $attributes, $amount, $occurredOn, $reopenMonth): Transaction {
            $user = $transaction->user;

            $this->allowChange->handle($user, $transaction->occurred_on, $reopenMonth);

            if (! $occurredOn->isSameMonth($transaction->occurred_on)) {
                $this->allowChange->handle($user, $occurredOn, $reopenMonth);
            }

            $transaction->update([
                ...$attributes,
                'occurred_on' => $occurredOn,
                'amount_cents' => $amount,
            ]);

            return $transaction;
        });
    }
}
