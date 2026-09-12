<?php

declare(strict_types=1);

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('records an expense from any page without navigating away', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $page = $this->actingAs($user)->visit('/settings/preferences');

    $page->click('@new-transaction')
        ->assertSee('New transaction')
        ->fill('amount', '12,50')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'Rent')
        ->click('@quick-add-save')
        // The page underneath never changed, which is the point of quick add.
        ->assertPathIs('/settings/preferences')
        ->assertNoJavascriptErrors();

    $this->actingAs($user)
        ->visit('/transactions')
        ->assertSee('Rent')
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
