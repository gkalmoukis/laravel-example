<?php

declare(strict_types=1);

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('explains a financial term where the reader first meets it', function (string $path, string $explanation): void {
    [$user] = userWithYear((int) date('Y'));

    // The explanation is in the page rather than only in a tooltip, so it reaches a screen
    // reader and a touch user too (UX-04, NFR-04).
    $this->actingAs($user)
        ->visit($path)
        ->assertSee($explanation)
        ->assertNoJavascriptErrors();
})->with(function (): array {
    $year = (int) date('Y');

    return [
        'cash flow' => [
            '/years/'.$year.'/cash-flow',
            'Money in minus money out for a period.',
        ],
        'forecast' => [
            '/years/'.$year.'/forecast',
            'Our best guess for the whole year',
        ],
        'comparison' => [
            '/years/'.$year.'/comparison',
            'The difference between Actual and Plan.',
        ],
        'months' => [
            '/years/'.$year.'/months',
            'Only finished months count as final.',
        ],
        'dashboard' => [
            '/dashboard',
            'Everything you own minus everything you owe.',
        ],
    ];
});

it('opens a glossary tooltip from the keyboard', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // A button rather than a bare span, so it is in the tab order and can be opened
    // without a mouse (NFR-04).
    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/cash-flow')
        ->click('@glossary-cashFlow')
        ->assertSee('Money in minus money out for a period.')
        ->assertNoJavascriptErrors();
});

it('turns wide tables into card lists on a phone', function (string $path, string $cards): void {
    [$user] = userWithYear((int) date('Y'));

    // Six columns at 375 px would either scroll sideways or shrink past reading (UX-13).
    $this->actingAs($user)
        ->visit($path)
        ->on()->mobile()
        ->assertPresent('[data-testid="'.$cards.'"]')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with(function (): array {
    $year = (int) date('Y');

    return [
        'cash flow' => ['/years/'.$year.'/cash-flow', 'cash-flow-cards'],
        'forecast' => ['/years/'.$year.'/forecast', 'forecast-month-cards'],
    ];
});

it('records a transaction without touching the mouse', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // Opened with the shortcut, filled by tabbing, saved with Enter (ACC-02, NFR-04).
    $this->actingAs($user)
        ->visit('/transactions')
        ->keys('@year-switcher', 'n')
        ->assertSee('New transaction')
        ->fill('amount', '18,40')
        ->click('@category-combobox')
        ->click('[data-slot="command-item"]:has-text("Housing")')
        ->fill('description', 'Keyboard entry')
        // A real form with a submit button, so Enter saves it (NFR-04).
        ->keys('description', 'Enter')
        ->assertSee('Keyboard entry')
        ->assertNoJavascriptErrors();
});
