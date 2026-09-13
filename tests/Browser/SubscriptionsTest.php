<?php

declare(strict_types=1);

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('plans a subscription and files a charge against it', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // The billing date defaults to today, so a monthly subscription is charged in every
    // month of the year.
    $this->actingAs($user)
        ->visit('/subscriptions')
        ->click('@add-subscription')
        ->fill('name', 'Netflix')
        ->fill('amount', '12,99')
        ->click('@save-subscription')
        ->assertSee('Netflix')
        ->assertSee('12,99')
        ->assertNoJavascriptErrors();

    // The plan expects it without anyone typing it into the budget: twelve times 12,99
    // (SUB-04).
    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/plan/expenses')
        ->assertSee('155,88')
        ->assertNoJavascriptErrors();

    // And a charge filed under the subcategory it created knows what it paid for
    // (SUB-02, SUB-06).
    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@new-transaction')
        ->fill('amount', '12,99')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Netflix")')
        ->fill('description', 'Netflix this month')
        ->click('@quick-add-save')
        ->assertSee('Netflix this month')
        ->assertNoJavascriptErrors();
});

it('stops a subscription and starts it again', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $page = $this->actingAs($user)
        ->visit('/subscriptions')
        ->click('@add-subscription')
        ->fill('name', 'Gym')
        ->fill('amount', '30,00')
        ->click('@save-subscription')
        ->assertSee('Gym');

    $page->click('button:has-text("Stop")')
        ->assertSee('Stopped')
        ->assertNoJavascriptErrors();

    $page->click('button:has-text("Start again")')
        ->assertDontSee('Stopped')
        ->assertNoJavascriptErrors();
});

it('manages subscriptions on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/subscriptions')
        ->on()->mobile()
        ->click('@add-subscription')
        ->fill('name', 'Spotify')
        ->fill('amount', '9,99')
        ->click('@save-subscription')
        ->assertSee('Spotify')
        ->assertSee('9,99')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('reaches subscriptions from the plan tabs and back', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $year = date('Y');

    $this->actingAs($user)
        ->visit('/years/'.$year.'/plan/income')
        ->assertSee($year.' plan')
        ->click('@tab-subscriptions')
        ->assertPathIs('/subscriptions')
        ->assertSee('Subscriptions')
        ->click('@tab-expenses')
        ->assertPathIs('/years/'.$year.'/plan/expenses')
        ->assertNoJavascriptErrors();
});

it('reads the subscriptions tab on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/subscriptions')
        ->on()->mobile()
        ->assertSee('Subscriptions')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
