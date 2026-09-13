<?php

declare(strict_types=1);

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('says what Fin is and offers one way in', function (string $width): void {
    $page = visit('/');

    if ($width === 'mobile') {
        $page->on()->mobile();
    }

    $page->assertSee('Fin')
        ->assertSee('planned, recorded and forecast')
        ->assertSeeLink('Sign in')
        // There is no public registration and a link to it would break the Wayfinder
        // build, so the page must never grow one (INV-01).
        ->assertDontSee('Sign up')
        ->assertDontSee('Register')
        ->assertDontSee('Create an account')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with(['desktop', 'mobile']);

it('sends a signed-in user to the dashboard instead', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/')
        ->assertSeeLink('Go to dashboard')
        ->assertDontSee('Sign in')
        ->assertNoJavascriptErrors();
});
