<?php

declare(strict_types=1);

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('offers a first plan to an account that has none', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->assertSee('Create your first plan')
        ->assertNoJavascriptErrors();
});

it('carries the chosen year from the plan to a screen without one', function (): void {
    [$user] = userWithYear(2027);

    $page = $this->actingAs($user)->visit('/years/2027/plan/income');

    $page->assertSee('2027 plan')->assertNoJavascriptErrors();

    // The settings address says nothing about a year, so the switcher has to remember it.
    $page->navigate('/settings/preferences')
        ->assertSee('2027')
        ->assertNoJavascriptErrors();
});

it('reaches the plan from the sidebar', function (): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->click('Plan')
        ->assertPathBeginsWith('/years/2027/plan')
        ->assertSee('2027 plan')
        ->assertNoJavascriptErrors();
});

it('keeps the year switcher reachable on a narrow screen', function (): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->on()->mobile()
        ->assertSee('2027')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('nudges a year whose setup was never finished', function (): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/years/2027/plan/income')
        ->assertSee('Finish setting up 2027')
        ->assertNoJavascriptErrors();
});
