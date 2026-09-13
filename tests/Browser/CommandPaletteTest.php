<?php

declare(strict_types=1);

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('opens with the shortcut and jumps to a screen', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // Seven sidebar entries cover twelve screens, so a tab inside a hub is two clicks
    // away. The palette reaches it by name (UX-05, NFR-06).
    $this->actingAs($user)
        ->visit('/dashboard')
        ->keys('@year-switcher', 'Control+k')
        ->assertSee('Go to')
        ->click('Reports · Cash flow')
        ->assertPathIs('/years/'.date('Y').'/reports/cash-flow')
        ->assertNoJavascriptErrors();
});

it('opens while the cursor is in a field', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // A modifier combination cannot be typed by accident, so unlike the bare N shortcut
    // this one stays available from inside the search box — where it is usually reached
    // for.
    $this->actingAs($user)
        ->visit('/transactions')
        ->fill('q', 'coffee')
        ->keys('q', 'Control+k')
        ->assertSee('Go to')
        ->assertNoJavascriptErrors();
});

it('switches the year from the palette', function (): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/dashboard')
        ->keys('@year-switcher', 'Control+k')
        ->assertSee('Go to')
        ->click('@palette-year-2027')
        ->assertPathIs('/years/2027/plan/income')
        ->assertNoJavascriptErrors();
});

it('starts a transaction from the palette', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/transactions')
        ->keys('@year-switcher', 'Control+k')
        ->click('@palette-new-transaction')
        ->assertSee('Type it the way you would say it')
        ->assertNoJavascriptErrors();
});

it('reaches the palette on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/dashboard')
        ->on()->mobile()
        ->keys('@year-switcher', 'Control+k')
        ->assertSee('Go to')
        ->assertSee('Transactions')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
