<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;

/*
 * The fifteen-second target (§2.3) and the core flows at both viewport sizes (UX-10,
 * NFR-06). Browser tests assert through the interface: the application runs in a separate
 * process, so a model re-read here would return a stale snapshot.
 */

function housingFor(User $user): int
{
    return $user->categories()
        ->where('name', 'Housing')
        ->whereNull('parent_id')
        ->firstOrFail()
        ->id;
}

it('records a transaction in six interactions or fewer', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $page = $this->actingAs($user)->visit('/transactions');

    // TST-05 is a budget, not a wish, so the steps are counted rather than described.
    // Everything else quick add offers — type, date, account, subcategory, notes — is
    // already answered when the sheet opens, which is what keeps the count this low.
    $interactions = [
        fn () => $page->click('@new-transaction'),
        fn () => $page->fill('#amount', '12,50'),
        fn () => $page->click('@category-combobox'),
        fn () => $page->click('[data-slot="command-item"]:has-text("Housing")'),
        fn () => $page->fill('description', 'Coffee'),
        fn () => $page->click('@quick-add-save'),
    ];

    expect($interactions)->toHaveCount(6);

    foreach ($interactions as $interaction) {
        $interaction();
    }

    $page->assertSee('Coffee')
        ->assertSee('12,50')
        ->assertNoJavascriptErrors();
});

it('records a transaction on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->on()->mobile()
        ->click('@new-transaction-fab')
        ->fill('#amount', '9,90')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'Bus fare')
        ->click('@quick-add-save')
        ->assertSee('Bus fare')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('lists transactions as cards on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => housingFor($user),
        'occurred_on' => date('Y-m-d'),
        'amount_cents' => 45_000,
        'description' => 'Rent',
    ]);

    // Five columns do not fit at 375 px, so the row becomes a card (UX-13, NFR-06).
    $this->actingAs($user)
        ->visit('/transactions')
        ->on()->mobile()
        ->assertSee('Rent')
        ->assertSee('450,00')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('refuses a finished month and then reopens it on the same screen', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    MonthClosure::factory()->for($year)->create([
        'month' => (int) date('n'),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('#amount', '20,00')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'Forgotten')
        ->click('@quick-add-save')
        ->assertSee('is marked complete')
        // The refusal is a dead end unless it offers the way out (TXV-02).
        ->click('@reopen-and-save-button')
        ->assertSee('Forgotten')
        ->assertNoJavascriptErrors();
});

it('refiles a batch on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    Transaction::factory()->count(2)->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Miscellaneous')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => date('Y-m-d'),
        'description' => 'Unsorted',
    ]);

    $this->actingAs($user)
        ->visit('/transactions')
        ->on()->mobile()
        ->click('@select-all-cards')
        ->assertSee('2 selected')
        ->click('@bulk-category')
        ->click('[role="option"]:has-text("Housing")')
        ->click('@apply-bulk-category')
        ->assertSee('Housing')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('shows the filtered list with nothing in it', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // Every list has an empty state with one next action (EDGE-01).
    $this->actingAs($user)
        ->visit('/transactions?q=nothing-matches-this')
        ->assertSee('Nothing matches these filters yet')
        ->assertSee('Clear the filters')
        ->assertNoJavascriptErrors();
});
