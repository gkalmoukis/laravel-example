<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\ValueObjects\Money;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('shows where the year is heading', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Salary')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Salary',
        'type' => 'income',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(200_000));

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/forecast')
        ->assertSee('Forecast')
        ->assertSee('Year end balance')
        // Twelve months of 2.000,00 with nothing going out.
        ->assertSee('24.000,00')
        ->assertSee('Month by month')
        ->assertSee('Based on')
        ->assertNoJavascriptErrors();
});

it('shows a dash rather than a rate when nothing is earned', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    // Dividing by no income has no answer, so the page says so (EDGE-03).
    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/forecast')
        ->assertSee('— of what you earn')
        ->assertNoJavascriptErrors();
});

it('reads the forecast on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/forecast')
        ->on()->mobile()
        ->assertSee('Forecast')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('sets up the emergency fund', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(100_000));

    $page = $this->actingAs($user)->visit('/goals/emergency-fund');

    // Six months of 1.000,00 essentials.
    $page->assertSee('Emergency fund')
        ->assertSee('6.000,00')
        ->assertSee('6 months ×')
        ->assertSee('Not reachable with the current plan')
        ->assertNoJavascriptErrors();

    $page->fill('monthly_contribution', '500,00')
        ->click('@save-emergency-fund')
        ->assertSee('On track for')
        ->assertNoJavascriptErrors();
});

it('reads the emergency fund on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/goals/emergency-fund')
        ->on()->mobile()
        ->assertSee('Emergency fund')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
