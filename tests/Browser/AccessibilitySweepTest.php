<?php

declare(strict_types=1);

use Tests\Fixtures\DemoYear;

/*
 * Every authenticated screen, checked against axe (NFR-06).
 *
 * This found real defects the moment it was written: two colours that did not meet the
 * contrast floor as text, six chart containers carrying an `aria-label` a plain div may
 * not have, four select triggers with no accessible name, and a tab row promising a
 * panel the page never rendered. A guard is worth more than a one-off audit.
 *
 * Browser tests assert through the interface. The application runs in a separate
 * process, so a model re-read here would return a stale snapshot.
 */

beforeEach(function (): void {
    [$this->user] = DemoYear::build();
});

it('has no accessibility issues on any screen', function (string $path): void {
    $this->actingAs($this->user)->visit($path)->assertNoAccessibilityIssues();
})->with(function (): array {
    $year = DemoYear::YEAR;

    return [
        '/dashboard', '/transactions', '/subscriptions', '/goals',
        '/goals/emergency-fund', '/goals/net-worth', '/years/create',
        '/years/'.$year.'/months', '/years/'.$year.'/months/1',
        '/years/'.$year.'/plan/income', '/years/'.$year.'/plan/expenses',
        '/years/'.$year.'/plan/irregular', '/years/'.$year.'/plan/opening',
        '/years/'.$year.'/reports', '/years/'.$year.'/reports/comparison',
        '/years/'.$year.'/reports/cash-flow', '/years/'.$year.'/reports/forecast',
        '/years/'.$year.'/setup/opening', '/years/'.$year.'/setup/income',
        '/years/'.$year.'/setup/expenses', '/settings/profile', '/settings/password',
        '/settings/appearance', '/settings/two-factor', '/settings/preferences',
        '/settings/categories', '/settings/accounts', '/settings/invitations',
    ];
});
