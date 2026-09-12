<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;

function closeMonth(FinancialYear $year, int $month): MonthClosure
{
    return MonthClosure::factory()->for($year)->create([
        'month' => $month,
        'completed_at' => now(),
    ]);
}

function marchExpense(User $user, FinancialYear $year): Transaction
{
    return Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->firstOrFail()->id,
        'occurred_on' => sprintf('%d-03-03', $year->year),
    ]);
}

/**
 * @return array<string, mixed>
 */
function marchPayload(User $user, array $overrides = []): array
{
    return [
        'type' => TransactionType::Expense->value,
        'amount' => '12,50',
        'occurred_on' => '2027-03-03',
        'category_id' => $user->categories()->where('name', 'Housing')->firstOrFail()->id,
        'description' => 'Rent',
        ...$overrides,
    ];
}

it('refuses a new transaction dated in a finished month', function (): void {
    [$user, $year] = userWithYear();

    closeMonth($year, 3);

    $this->actingAs($user)
        ->post(route('transactions.store'), marchPayload($user))
        ->assertSessionHasErrors([
            'occurred_on' => 'March 2027 is marked complete. Reopen it to save this change.',
            'month_complete' => '3',
        ]);

    expect($user->transactions()->count())->toBe(0);
});

it('reopens the month and saves in one go when asked', function (): void {
    [$user, $year] = userWithYear();

    $closure = closeMonth($year, 3);

    $this->actingAs($user)
        ->post(route('transactions.store'), marchPayload($user, ['reopen_month' => true]))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->count())->toBe(1)
        ->and($closure->refresh()->completed_at)->toBeNull();
});

it('leaves other months closed when one is reopened', function (): void {
    [$user, $year] = userWithYear();

    $march = closeMonth($year, 3);
    $april = closeMonth($year, 4);

    $this->actingAs($user)->post(route('transactions.store'), marchPayload($user, ['reopen_month' => true]));

    expect($march->refresh()->completed_at)->toBeNull()
        ->and($april->refresh()->completed_at)->not->toBeNull();
});

it('refuses an edit inside a finished month', function (): void {
    [$user, $year] = userWithYear();

    $transaction = marchExpense($user, $year);

    closeMonth($year, 3);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), marchPayload($user, ['amount' => '99,00']))
        ->assertSessionHasErrors('occurred_on');

    expect($transaction->refresh()->amount_cents->cents)->not->toBe(9900);
});

it('refuses moving a transaction into a finished month', function (): void {
    [$user, $year] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->firstOrFail()->id,
        'occurred_on' => '2027-05-05',
    ]);

    closeMonth($year, 3);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), marchPayload($user))
        ->assertSessionHasErrors('occurred_on');

    expect($transaction->refresh()->occurred_on->format('Y-m-d'))->toBe('2027-05-05');
});

it('refuses deleting from a finished month', function (): void {
    [$user, $year] = userWithYear();

    $transaction = marchExpense($user, $year);

    closeMonth($year, 3);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction))
        ->assertSessionHasErrors('occurred_on');

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});

it('deletes from a finished month when asked to reopen it', function (): void {
    [$user, $year] = userWithYear();

    $transaction = marchExpense($user, $year);
    $closure = closeMonth($year, 3);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction), ['reopen_month' => true])
        ->assertSessionHasNoErrors();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse()
        ->and($closure->refresh()->completed_at)->toBeNull();
});

it('lets an unfinished month be edited freely', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create(['month' => 3, 'completed_at' => null]);

    $this->actingAs($user)
        ->post(route('transactions.store'), marchPayload($user))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->count())->toBe(1);
});

it('has nothing to guard in a year with no plan at all', function (): void {
    [$user, $year] = userWithYear();

    closeMonth($year, 3);

    // 2029 has no plan, so no month in it can be finished and the date is simply
    // accepted — flagged on read rather than refused here (TXQ-08).
    $this->actingAs($user)
        ->post(route('transactions.store'), marchPayload($user, ['occurred_on' => '2029-03-03']))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->count())->toBe(1);
});

it('knows whether a closure means the month is finished', function (): void {
    [, $year] = userWithYear();

    $open = MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => null]);
    $finished = closeMonth($year, 2);

    expect($open->isComplete())->toBeFalse()
        ->and($finished->isComplete())->toBeTrue();
});
