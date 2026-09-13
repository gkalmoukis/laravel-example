<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\MonthClosure;
use App\Models\Transaction;

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

it('shows the year month by month', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    MonthClosure::factory()->for($year)->create([
        'month' => 1,
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->click('Months')
        ->assertPathBeginsWith('/years/')
        ->assertSee('month by month')
        ->assertSee('Complete')
        ->assertSee('Not started')
        ->assertNoJavascriptErrors();
});

it('reads the month cards on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/months')
        ->on()->mobile()
        ->assertSee('month by month')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('opens a month and reads how it went', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $housing = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $housing->id,
        'occurred_on' => date('Y-m-05'),
        'amount_cents' => 45_000,
        'description' => 'Rent',
    ]);

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/months/'.(int) date('n'))
        ->assertSee('Totals')
        ->assertSee('Anything to fix')
        ->assertSee('Where it went against the plan')
        ->assertSee('450,00')
        ->assertNoJavascriptErrors();
});

it('expands a category to see what it was spent on', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $food = $user->categories()->where('name', 'Food & Groceries')->whereNull('parent_id')->firstOrFail();
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
        'occurred_on' => date('Y-m-05'),
        'amount_cents' => 3_000,
    ]);

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/months/'.(int) date('n'))
        ->click('Food & Groceries')
        ->assertSee('Supermarket')
        ->assertNoJavascriptErrors();
});

it('reviews a month on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/months/'.(int) date('n'))
        ->on()->mobile()
        ->assertSee('Totals')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
