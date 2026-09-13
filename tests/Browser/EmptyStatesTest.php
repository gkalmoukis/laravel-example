<?php

declare(strict_types=1);

use App\Actions\CompleteYearSetup;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('offers one way forward on every empty list', function (string $path, string $action): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit($path)
        ->assertSee($action)
        ->assertNoJavascriptErrors();
})->with([
    // Goals are deliberately absent: every account keeps an emergency fund goal that
    // cannot be archived, so that list is never empty (GOAL-01).
    'transactions' => ['/transactions', 'Clear the filters'],
    'subscriptions' => ['/subscriptions', 'Add a subscription'],
]);

it('says a year was never finished, wherever that year is on screen', function (string $path): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit($path)
        ->assertSee('Setup unfinished')
        ->assertSee('Finish setting up')
        ->assertNoJavascriptErrors();
})->with(function (): array {
    $year = (int) date('Y');

    return [
        'dashboard' => ['/dashboard'],
        'months' => ['/years/'.$year.'/months'],
        'comparison' => ['/years/'.$year.'/reports/comparison'],
        'cash flow' => ['/years/'.$year.'/reports/cash-flow'],
        'forecast' => ['/years/'.$year.'/reports/forecast'],
    ];
});

it('drops the banner once the year has been set up', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    resolve(CompleteYearSetup::class)->handle($year);

    $this->actingAs($user)
        ->visit('/dashboard')
        ->assertSee('at a glance')
        ->assertDontSee('Setup unfinished')
        ->assertNoJavascriptErrors();
});

it('reads the empty states on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/subscriptions')
        ->on()->mobile()
        ->assertSee('Nothing on a schedule yet')
        ->assertSee('Add a subscription')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('says what to do next rather than only that a list is empty', function (): void {
    [$user] = userWithYear(2027);

    // EDGE-01: four screens said "nothing here" and stopped, leaving the user to work
    // out what they were supposed to do.
    $this->actingAs($user)
        ->visit('/years/2027/plan/irregular')
        ->assertSee('Nothing irregular planned yet')
        ->assertSee('Add a holiday, an annual insurance or a tax bill')
        ->assertNoJavascriptErrors();

    // Income says the same for its own side of the plan. Accounts and categories carry
    // the same treatment, but every user is provisioned with one of each, so those rows
    // are defensive rather than reachable.
    $this->actingAs($user)
        ->visit('/years/2027/plan/income')
        ->assertSee('No income planned yet')
        ->assertSee('Add a salary or a freelance line')
        ->assertNoJavascriptErrors();
});

it('writes a share as a dash when there is nothing to divide by', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // EDGE-03: a year with no income has no savings rate. The dashboard and the forecast
    // used to disagree about this — one dashed, the other divided anyway.
    $this->actingAs($user)
        ->visit('/dashboard')
        ->assertSee('Savings rate')
        ->assertSee('—')
        ->assertNoJavascriptErrors();

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/reports/forecast')
        ->assertSee('of what you earn')
        ->assertSee('—')
        ->assertNoJavascriptErrors();
});

it('holds the shape of a chart while it loads', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/dashboard')
        ->assertSee('Closing balance')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
