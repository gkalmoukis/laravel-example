<?php

declare(strict_types=1);

use App\Enums\EntrySource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;

function txCategory(User $user, string $name = 'Housing'): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function validTransaction(User $user, array $overrides = []): array
{
    return [
        'type' => TransactionType::Expense->value,
        'amount' => '12,50',
        'occurred_on' => '2027-03-03',
        'category_id' => txCategory($user)->id,
        'description' => 'Rent',
        ...$overrides,
    ];
}

it('records a transaction', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user))
        ->assertRedirect();

    $transaction = $user->transactions()->sole();

    expect($transaction->amount_cents->cents)->toBe(1250)
        ->and($transaction->description)->toBe('Rent')
        ->and($transaction->occurred_on->format('Y-m-d'))->toBe('2027-03-03')
        ->and($transaction->type)->toBe(TransactionType::Expense);
});

it('defaults the entry source to the full form', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)->post(route('transactions.store'), validTransaction($user));

    expect($user->transactions()->sole()->entry_source)->toBe(EntrySource::Form);
});

it('keeps how the transaction was entered and how long it took', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)->post(route('transactions.store'), validTransaction($user, [
        'entry_source' => EntrySource::QuickAdd->value,
        'entry_duration_ms' => 8_400,
    ]));

    $transaction = $user->transactions()->sole();

    expect($transaction->entry_source)->toBe(EntrySource::QuickAdd)
        ->and($transaction->entry_duration_ms)->toBe(8_400);
});

it('accepts every amount format a person might type', function (string $typed, int $cents): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['amount' => $typed]))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->amount_cents->cents)->toBe($cents);
})->with([
    ['12,50', 1250],
    ['12.50', 1250],
    ['1.234,56', 123_456],
    ['1234.56', 123_456],
    ['1.234,56 €', 123_456],
]);

it('refuses an amount that is not a number', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['amount' => 'a lot']))
        ->assertSessionHasErrors(['amount' => 'Enter an amount like 1.234,56.']);
});

it('refuses an amount of nothing', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['amount' => '0,00']))
        ->assertSessionHasErrors(['amount' => 'Enter an amount greater than zero.']);
});

it('says so when the amount is beyond what it can hold', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['amount' => '99.999.999.999,00']))
        ->assertSessionHasErrors(['amount' => 'That amount is too large.']);
});

it('refuses a category that records the opposite direction', function (): void {
    [$user] = userWithYear();

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['category_id' => $salary->id]))
        ->assertSessionHasErrors(['category_id' => 'Choose a category for expense.']);
});

it('refuses a subcategory as the category', function (): void {
    [$user] = userWithYear();

    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['category_id' => $supermarket->id]))
        ->assertSessionHasErrors('category_id');
});

it('refuses a deactivated category', function (): void {
    [$user] = userWithYear();

    $category = txCategory($user);
    $category->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['category_id' => $category->id]))
        ->assertSessionHasErrors('category_id');
});

it('refuses a subcategory belonging to a different category', function (): void {
    [$user] = userWithYear();

    $coffee = $user->categories()->where('name', 'Coffee')->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['subcategory_id' => $coffee->id]))
        ->assertSessionHasErrors('subcategory_id');
});

it('accepts a subcategory of the chosen category', function (): void {
    [$user] = userWithYear();

    $food = txCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, [
            'category_id' => $food->id,
            'subcategory_id' => $supermarket->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subcategory_id)->toBe($supermarket->id);
});

it('refuses a deactivated account', function (): void {
    [$user] = userWithYear();

    $account = $user->accounts()->firstOrFail();
    $account->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['account_id' => $account->id]))
        ->assertSessionHasErrors('account_id');
});

it('refuses a description longer than the column holds', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['description' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('description');
});

it('refuses notes longer than the form allows', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['notes' => str_repeat('a', 2001)]))
        ->assertSessionHasErrors('notes');
});

it('refuses a missing date', function (): void {
    [$user] = userWithYear();

    $payload = validTransaction($user);
    unset($payload['occurred_on']);

    $this->actingAs($user)
        ->post(route('transactions.store'), $payload)
        ->assertSessionHasErrors('occurred_on');
});

it('corrects a transaction', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, [
            'amount' => '40,00',
            'description' => 'Rent, corrected',
        ]))
        ->assertRedirect();

    expect($transaction->refresh()->amount_cents->cents)->toBe(4000)
        ->and($transaction->description)->toBe('Rent, corrected');
});

it('deletes a transaction for good', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse();
});

it('records against an account and a subscription-free subcategory', function (): void {
    [$user] = userWithYear();

    $account = Account::factory()->for($user)->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(route('transactions.store'), validTransaction($user, ['account_id' => $account->id]))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->account_id)->toBe($account->id);
});

it('refuses a transaction with no category at all', function (): void {
    [$user] = userWithYear();

    $payload = validTransaction($user);
    unset($payload['category_id']);

    $this->actingAs($user)
        ->post(route('transactions.store'), $payload)
        ->assertSessionHasErrors('category_id');
});

it('refuses an edit whose amount is not a number', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, ['amount' => 'lots']))
        ->assertSessionHasErrors(['amount' => 'Enter an amount like 1.234,56.']);
});

it('remembers which account the money moved through', function (): void {
    [$user] = userWithYear();

    $account = Account::factory()->for($user)->create(['name' => 'Everyday', 'is_active' => true]);

    $this->actingAs($user)->post(route('transactions.store'), validTransaction($user, [
        'account_id' => $account->id,
    ]));

    expect($user->transactions()->sole()->account?->name)->toBe('Everyday');
});

it('refuses an edit that zeroes the amount', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, ['amount' => '0']))
        ->assertSessionHasErrors(['amount' => 'Enter an amount greater than zero.']);
});

it('refuses an edit into a category recording the opposite direction', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, ['category_id' => $salary->id]))
        ->assertSessionHasErrors(['category_id' => 'Choose a category for expense.']);
});

it('refuses an edit that drops the category', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $payload = validTransaction($user);
    unset($payload['category_id']);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), $payload)
        ->assertSessionHasErrors('category_id');
});

it('refuses an edit to an amount beyond what it can hold', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, ['amount' => '99.999.999.999,00']))
        ->assertSessionHasErrors(['amount' => 'That amount is too large.']);
});

it('duplicates a transaction as a new one dated today', function (): void {
    [$user] = userWithYear();

    $original = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
        'amount_cents' => 1_250,
        'description' => 'Weekly shop',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), validTransaction($user, [
        'amount' => '12,50',
        'description' => 'Weekly shop',
        'occurred_on' => '2027-06-10',
        'entry_source' => EntrySource::Duplicate->value,
    ]))->assertSessionHasNoErrors();

    $copy = $user->transactions()->whereKeyNot($original->id)->sole();

    expect($copy->entry_source)->toBe(EntrySource::Duplicate)
        ->and($copy->description)->toBe('Weekly shop')
        ->and($copy->occurred_on->format('Y-m-d'))->toBe('2027-06-10')
        // The original is untouched: duplicating adds, it never moves.
        ->and($original->refresh()->occurred_on->format('Y-m-d'))->toBe('2027-03-03');
});

it('keeps an edit from changing which account it belongs to', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear();

    $transaction = Transaction::factory()->for($owner)->create([
        'category_id' => txCategory($owner)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($intruder)
        ->patch(route('transactions.update', $transaction), validTransaction($intruder))
        ->assertNotFound();

    expect($transaction->refresh()->user_id)->toBe($owner->id);
});

it('moves a transaction to another month', function (): void {
    [$user] = userWithYear();

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => txCategory($user)->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, [
            'occurred_on' => '2027-09-09',
        ]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->occurred_on->format('Y-m-d'))->toBe('2027-09-09');
});

it('changes which subcategory an edit files it under', function (): void {
    [$user] = userWithYear();

    $food = txCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();
    $laiki = $user->categories()->where('name', "Farmers' market (Laiki)")->firstOrFail();

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, [
            'category_id' => $food->id,
            'subcategory_id' => $laiki->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->subcategory_id)->toBe($laiki->id);
});

it('clears the subcategory when an edit drops it', function (): void {
    [$user] = userWithYear();

    $food = txCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
        'occurred_on' => '2027-03-03',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), validTransaction($user, [
            'category_id' => $food->id,
            'subcategory_id' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->subcategory_id)->toBeNull();
});

it('can be linked to the subscription it paid for', function (): void {
    [$user] = userWithYear();

    $subscriptions = $user->categories()->where('name', 'Subscriptions')->firstOrFail();

    $subscription = Subscription::factory()->for($user)->create([
        'category_id' => $subscriptions->id,
    ]);

    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => $subscriptions->id,
        'subscription_id' => $subscription->id,
        'occurred_on' => '2027-03-03',
    ]);

    expect($transaction->subscription?->id)->toBe($subscription->id);
});
