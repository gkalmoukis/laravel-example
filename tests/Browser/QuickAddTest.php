<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\MonthClosure;
use App\Models\Transaction;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('is reachable from a page that has nothing to do with transactions', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // TXQ-01: the button is on every authenticated page, and opening it loads the form
    // in place rather than navigating. Recording itself is covered by the tests below,
    // which run on the transaction list.
    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->click('@new-transaction')
        ->assertSee('New transaction')
        ->assertSee('Pick a category')
        ->assertPathIs('/settings/preferences')
        ->assertNoJavascriptErrors();
});

it('accepts a comma as the decimal separator', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('amount', '1.234,56')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'Big one')
        ->click('@quick-add-save')
        ->assertSee('Big one')
        ->assertSee('1.234,56')
        ->assertNoJavascriptErrors();
});

it('files a transaction under a subcategory in one choice', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // The combobox offers subcategories as choices in their own right, so filing under
    // Food & Groceries › Supermarket is one pick rather than two (TXQ-04).
    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('amount', '30,00')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Supermarket")')
        ->fill('description', 'Weekly shop')
        ->click('@quick-add-save')
        ->assertSee('Weekly shop')
        ->assertSee('Supermarket')
        ->assertNoJavascriptErrors();
});

it('keeps the sheet open for the next one', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('amount', '5,00')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'First')
        ->click('@quick-add-save-another')
        // Still open, and the amount has been cleared ready for the next entry.
        ->assertSee('New transaction')
        ->assertValue('amount', '')
        ->assertNoJavascriptErrors();
});

it('warns that a year with no plan will not be counted', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('occurred_on', '2029-03-03')
        ->assertSee("You don't have a 2029 plan yet")
        ->assertNoJavascriptErrors();
});

it('opens from the keyboard on a desktop', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->keys('@year-switcher', 'n')
        // The hint only exists inside the sheet; the button in the top bar carries the
        // same words as the title, so it would not prove anything on its own.
        ->assertSee('Type it the way you would say it')
        ->assertNoJavascriptErrors();
});

it('leaves the shortcut alone while the user is writing', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->fill('q', 'dinner')
        ->keys('q', 'n')
        ->assertDontSee('Type it the way you would say it')
        ->assertNoJavascriptErrors();
});

it('reaches quick add from a thumb on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->on()->mobile()
        ->click('@new-transaction-fab')
        ->assertSee('New transaction')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('corrects a transaction in the same form', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => date('Y-m-d'),
        'amount_cents' => 1_250,
        'description' => 'Rnt',
    ]);

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@row-actions-'.$transaction->id)
        ->click('@edit-'.$transaction->id)
        ->assertSee('Edit transaction')
        // Prefilled from the transaction rather than blank (TXF-01).
        ->assertValue('description', 'Rnt')
        ->fill('description', 'Rent')
        ->click('@quick-add-save')
        ->assertSee('Rent')
        ->assertNoJavascriptErrors();
});

it('duplicates a transaction with today as the date', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => '2020-01-05',
        'amount_cents' => 1_250,
        'description' => 'Coffee',
    ]);

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@row-actions-'.$transaction->id)
        ->click('@duplicate-'.$transaction->id)
        ->assertSee('Duplicate transaction')
        ->assertValue('description', 'Coffee')
        // Dated today rather than carried over from the original (TXF-02). The exact day
        // is the browser's, in the user's timezone, so it is not compared against PHP's.
        ->assertDontSee('2020-01-05')
        ->click('@quick-add-save')
        ->assertNoJavascriptErrors();

    // Two of them now, which is the whole point of duplicating. The footer totals the
    // filter, so twice 12,50 is what says the copy exists alongside the original.
    $this->actingAs($user)
        ->visit('/transactions?q=Coffee')
        ->assertSee('25,00')
        ->assertNoJavascriptErrors();
});

it('asks before deleting, naming what it would remove', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => date('Y-m-d'),
        'amount_cents' => 1_250,
        'description' => 'Mistake',
    ]);

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@row-actions-'.$transaction->id)
        ->click('@delete-'.$transaction->id)
        ->assertSee('Delete this')
        ->assertSee('This cannot be undone')
        ->click('@confirm-delete')
        ->assertSee('Nothing matches these filters yet')
        ->assertNoJavascriptErrors();
});

it('offers to reopen a finished month rather than refusing outright', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    MonthClosure::factory()->for($year)->create([
        'month' => (int) date('n'),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('amount', '9,99')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'Late entry')
        ->click('@quick-add-save')
        ->assertSee('is marked complete')
        ->click('@reopen-and-save-button')
        ->assertSee('Late entry')
        ->assertNoJavascriptErrors();
});
