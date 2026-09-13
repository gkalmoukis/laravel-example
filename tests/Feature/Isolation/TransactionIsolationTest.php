<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

/*
 * Another user's record is reported as missing rather than forbidden, so nothing leaks —
 * not even that it exists (USR-02, USR-03).
 */

function otherUsersExpense(User $owner): Transaction
{
    return Transaction::factory()->for($owner)->create([
        'type' => TransactionType::Expense,
        'category_id' => $owner->categories()->where('name', 'Housing')->firstOrFail()->id,
        'occurred_on' => '2027-03-03',
    ]);
}

it('reports another user transaction as missing when edited', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear();

    $transaction = otherUsersExpense($owner);

    $this->actingAs($intruder)
        ->patch(route('transactions.update', $transaction), [
            'type' => TransactionType::Expense->value,
            'amount' => '1,00',
            'occurred_on' => '2027-03-03',
            'category_id' => $intruder->categories()->where('name', 'Housing')->firstOrFail()->id,
            'description' => 'Not mine',
        ])
        ->assertNotFound();

    expect($transaction->refresh()->amount_cents->cents)->not->toBe(100);
});

it('reports another user transaction as missing when deleted', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear();

    $transaction = otherUsersExpense($owner);

    $this->actingAs($intruder)
        ->delete(route('transactions.destroy', $transaction))
        ->assertNotFound();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});

it('refuses to file a transaction under another user category', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear();

    $this->actingAs($intruder)
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => '1,00',
            'occurred_on' => '2027-03-03',
            'category_id' => $owner->categories()->where('name', 'Housing')->firstOrFail()->id,
            'description' => 'Borrowed category',
        ])
        ->assertSessionHasErrors('category_id');

    expect($intruder->transactions()->count())->toBe(0);
});

it('refuses to file a transaction against another user account', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear();

    $account = Account::factory()->for($owner)->create(['is_active' => true]);

    $this->actingAs($intruder)
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => '1,00',
            'occurred_on' => '2027-03-03',
            'category_id' => $intruder->categories()->where('name', 'Housing')->firstOrFail()->id,
            'account_id' => $account->id,
            'description' => 'Borrowed account',
        ])
        ->assertSessionHasErrors('account_id');
});

it('refuses to file a transaction under another user subcategory', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear();

    $this->actingAs($intruder)
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => '1,00',
            'occurred_on' => '2027-03-03',
            'category_id' => $intruder->categories()->where('name', 'Food & Groceries')->whereNull('parent_id')->firstOrFail()->id,
            'subcategory_id' => $owner->categories()->where('name', 'Supermarket')->firstOrFail()->id,
            'description' => 'Borrowed subcategory',
        ])
        ->assertSessionHasErrors('subcategory_id');
});
