<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\MoveSubcategory;
use App\Enums\TransactionIssue;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Facades\Schema;

/*
 * Issues are worked out on read rather than stored, so they appear and disappear as the
 * data around them changes (TXV-03, TXV-04).
 */

it('flags a transaction dated in a year with no plan', function (): void {
    [$user, $year] = userWithYear(2027);

    $transaction = Transaction::factory()
        ->in($user->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2029-03-15')
        ->create();

    expect($transaction->issues())->toBe([TransactionIssue::NoFinancialYear])
        ->and($transaction->isFlagged())->toBeTrue();
});

it('un-flags it the moment that year is created', function (): void {
    [$user] = userWithYear(2027);

    $transaction = Transaction::factory()
        ->in($user->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2029-03-15')
        ->create();

    expect($transaction->isFlagged())->toBeTrue();

    resolve(CreateFinancialYear::class)->handle($user, 2029);

    // Nothing about the transaction changed; the cause of the issue did.
    expect($transaction->refresh()->issues())->toBe([]);
});

it('flags a transaction whose category records the opposite direction', function (): void {
    [$user] = userWithYear(2027);

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    $transaction = Transaction::factory()->for($user)->on('2027-03-15')->create([
        'type' => TransactionType::Expense,
        'category_id' => $salary->id,
    ]);

    expect($transaction->issues())->toBe([TransactionIssue::CategoryTypeMismatch]);
});

it('flags a subcategory that has been moved out from under its category', function (): void {
    [$user] = userWithYear(2027);

    $food = $user->categories()->where('name', 'Food & Groceries')->firstOrFail();
    $dining = $user->categories()->where('name', 'Dining Out')->firstOrFail();
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $transaction = Transaction::factory()->for($user)->on('2027-03-15')->create([
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
    ]);

    expect($transaction->issues())->toBe([]);

    // The user moves Supermarket under Dining Out without bringing its history along.
    resolve(MoveSubcategory::class)->handle($supermarket, $dining, updateExisting: false);

    expect($transaction->refresh()->issues())->toBe([TransactionIssue::SubcategoryParentMismatch]);
});

it('reports every issue a transaction has at once', function (): void {
    [$user] = userWithYear(2027);

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    $transaction = Transaction::factory()->for($user)->on('2029-03-15')->create([
        'type' => TransactionType::Expense,
        'category_id' => $salary->id,
        'subcategory_id' => $supermarket->id,
    ]);

    expect($transaction->issues())->toBe([
        TransactionIssue::NoFinancialYear,
        TransactionIssue::CategoryTypeMismatch,
        TransactionIssue::SubcategoryParentMismatch,
    ]);
});

it('counts only sound transactions', function (): void {
    [$user] = userWithYear(2027);

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();
    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    // Sound.
    Transaction::factory()->in($housing)->on('2027-03-15')->ofCents(1000)->create();
    // Dated in a year with no plan.
    Transaction::factory()->in($housing)->on('2029-03-15')->ofCents(2000)->create();
    // Filed under a category recording the opposite direction.
    Transaction::factory()->for($user)->on('2027-04-01')->ofCents(4000)->create([
        'type' => TransactionType::Expense,
        'category_id' => $salary->id,
    ]);

    expect(Transaction::valid()->count())->toBe(1)
        ->and((int) Transaction::valid()->sum('amount_cents'))->toBe(1000)
        ->and(Transaction::flagged()->count())->toBe(2);
});

it('exposes the issues as columns without storing them', function (): void {
    [$user] = userWithYear(2027);

    Transaction::factory()
        ->in($user->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2029-03-15')
        ->create();

    $row = Transaction::withIssues()->firstOrFail();

    expect((bool) $row->getAttribute('has_no_financial_year'))->toBeTrue()
        ->and((bool) $row->getAttribute('has_category_type_mismatch'))->toBeFalse()
        ->and((bool) $row->getAttribute('has_subcategory_parent_mismatch'))->toBeFalse()
        // Nothing was written: the flags exist only in this query's result.
        ->and(Schema::hasColumn('transactions', 'has_no_financial_year'))->toBeFalse();
});

it('treats a transaction with no subcategory as sound', function (): void {
    [$user] = userWithYear(2027);

    Transaction::factory()
        ->in($user->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2027-06-01')
        ->create(['subcategory_id' => null]);

    expect(Transaction::valid()->count())->toBe(1)
        ->and(Transaction::flagged()->count())->toBe(0);
});

it('counts a transaction dated in a future month of a planned year', function (): void {
    [$user] = userWithYear(2027);

    Transaction::factory()
        ->in($user->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2027-12-31')
        ->create();

    // A future date is allowed and counts for its own month (EDGE-04).
    expect(Transaction::valid()->count())->toBe(1);
});

it("separates one user's transactions from another's", function (): void {
    [$user] = userWithYear(2027);
    [$other] = userWithYear(2027);

    Transaction::factory()->in($user->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2027-03-15')->create();
    Transaction::factory()->in($other->categories()->where('name', 'Housing')->firstOrFail())
        ->on('2027-03-15')->create();

    expect(Transaction::valid()->where('transactions.user_id', $user->id)->count())->toBe(1);
});
