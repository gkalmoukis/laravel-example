<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;

function bulkCategory(User $user, string $name): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

function expenseOn(User $user, string $date, string $description = 'Thing'): Transaction
{
    return Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => bulkCategory($user, 'Miscellaneous')->id,
        'occurred_on' => $date,
        'description' => $description,
    ]);
}

it('refiles every selected transaction', function (): void {
    [$user] = userWithYear();

    $one = expenseOn($user, '2027-03-01');
    $two = expenseOn($user, '2027-03-02');
    $housing = bulkCategory($user, 'Housing');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$one->id, $two->id],
            'category_id' => $housing->id,
        ])
        ->assertSessionHas('status', 'Updated 2 transactions.');

    expect($one->refresh()->category_id)->toBe($housing->id)
        ->and($two->refresh()->category_id)->toBe($housing->id);
});

it('counts one transaction in the singular', function (): void {
    [$user] = userWithYear();

    $one = expenseOn($user, '2027-03-01');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$one->id],
            'category_id' => bulkCategory($user, 'Housing')->id,
        ])
        ->assertSessionHas('status', 'Updated 1 transaction.');
});

it('sets the subcategory alongside the category', function (): void {
    [$user] = userWithYear();

    $one = expenseOn($user, '2027-03-01');
    $food = bulkCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$one->id],
            'category_id' => $food->id,
            'subcategory_id' => $supermarket->id,
        ])
        ->assertSessionHasNoErrors();

    expect($one->refresh()->subcategory_id)->toBe($supermarket->id);
});

it('clears a stale subcategory when none is chosen', function (): void {
    [$user] = userWithYear();

    $food = bulkCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $one = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
        'occurred_on' => '2027-03-01',
    ]);

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$one->id],
            'category_id' => bulkCategory($user, 'Housing')->id,
        ])
        ->assertSessionHasNoErrors();

    // The old subcategory belonged to the old category, so carrying it over would leave
    // the transaction filed under a subcategory of somewhere else.
    expect($one->refresh()->subcategory_id)->toBeNull();
});

it('leaves transactions in a finished month alone and says so', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create(['month' => 3, 'completed_at' => now()]);

    $finished = expenseOn($user, '2027-03-01');
    $open = expenseOn($user, '2027-04-01');
    $housing = bulkCategory($user, 'Housing');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$finished->id, $open->id],
            'category_id' => $housing->id,
        ])
        ->assertSessionHas('status', 'Updated 1 transaction. Skipped 1 in completed months.');

    expect($open->refresh()->category_id)->toBe($housing->id)
        ->and($finished->refresh()->category_id)->not->toBe($housing->id);
});

it('refuses to reverse the direction of a transaction', function (): void {
    [$user] = userWithYear();

    $expense = expenseOn($user, '2027-03-01');
    $salary = bulkCategory($user, 'Salary');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$expense->id],
            'category_id' => $salary->id,
        ])
        ->assertSessionHas('status', 'Updated 0 transactions. Skipped 1 that record money moving the other way.');

    expect($expense->refresh()->category_id)->not->toBe($salary->id);
});

it('reports both kinds of skip together', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create(['month' => 3, 'completed_at' => now()]);

    $finished = expenseOn($user, '2027-03-01');
    $income = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'category_id' => bulkCategory($user, 'Salary')->id,
        'occurred_on' => '2027-04-01',
    ]);
    $movable = expenseOn($user, '2027-04-02');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$finished->id, $income->id, $movable->id],
            'category_id' => bulkCategory($user, 'Housing')->id,
        ])
        ->assertSessionHas(
            'status',
            'Updated 1 transaction. Skipped 1 in completed months. Skipped 1 that record money moving the other way.',
        );
});

it('never touches another user transactions', function (): void {
    [$user] = userWithYear();
    [$other] = userWithYear();

    $mine = expenseOn($user, '2027-03-01');
    $theirs = expenseOn($other, '2027-03-01');
    $housing = bulkCategory($user, 'Housing');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$mine->id, $theirs->id],
            'category_id' => $housing->id,
        ])
        // Their row is not refused, it is simply invisible: the batch is read through the
        // user's own relationship, so it was never a candidate (USR-02).
        ->assertSessionHas('status', 'Updated 1 transaction.');

    expect($mine->refresh()->category_id)->toBe($housing->id)
        ->and($theirs->refresh()->category_id)->not->toBe($housing->id);
});

it('refuses another user category as the destination', function (): void {
    [$user] = userWithYear();
    [$other] = userWithYear();

    $mine = expenseOn($user, '2027-03-01');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$mine->id],
            'category_id' => bulkCategory($other, 'Housing')->id,
        ])
        ->assertSessionHasErrors('category_id');
});

it('refuses a subcategory of a different category', function (): void {
    [$user] = userWithYear();

    $mine = expenseOn($user, '2027-03-01');
    $coffee = $user->categories()->where('name', 'Coffee')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$mine->id],
            'category_id' => bulkCategory($user, 'Housing')->id,
            'subcategory_id' => $coffee->id,
        ])
        ->assertSessionHasErrors('subcategory_id');
});

it('refuses an empty selection', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [],
            'category_id' => bulkCategory($user, 'Housing')->id,
        ])
        ->assertSessionHasErrors('transaction_ids');
});

it('ignores selections that are not numbers', function (): void {
    [$user] = userWithYear();

    $one = expenseOn($user, '2027-03-01');

    $this->actingAs($user)
        ->patch(route('transaction-category.update'), [
            'transaction_ids' => [$one->id, 'nonsense'],
            'category_id' => bulkCategory($user, 'Housing')->id,
        ])
        ->assertSessionHasErrors('transaction_ids.1');
});
