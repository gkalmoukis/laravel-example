<?php

declare(strict_types=1);

use App\Enums\NetWorthItemKind;
use App\Models\NetWorthSnapshot;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('shows what the user owns and owes', function (): void {
    [$user, $year] = userWithYear((int) date('Y'));

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();
    $debt = $user->netWorthItems()->where('kind', NetWorthItemKind::Debt)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('financial_year_id', $year->id)
        ->where('net_worth_item_id', $cash->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 500_000]);

    NetWorthSnapshot::query()
        ->where('financial_year_id', $year->id)
        ->where('net_worth_item_id', $debt->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 200_000]);

    $this->actingAs($user)
        ->visit('/net-worth')
        ->assertSee('Net worth')
        // 5.000,00 owned less 2.000,00 owed.
        ->assertSee('3.000,00')
        ->assertSee('What it is made of')
        ->assertNoJavascriptErrors();
});

it('reads the year as a chart or as a table', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/net-worth')
        ->click('@toggle-chart-table')
        ->assertSee('View as chart')
        ->assertNoJavascriptErrors();
});

it('adds something the user owns', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/net-worth')
        ->click('@add-holding')
        ->fill('name', 'Flat')
        ->click('@save-holding')
        ->assertSee('Flat')
        ->assertNoJavascriptErrors();
});

it('retires something the user no longer has', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $investment = $user->netWorthItems()->where('kind', NetWorthItemKind::Investment)->firstOrFail();

    $this->actingAs($user)
        ->visit('/net-worth')
        ->click('@retire-'.$investment->id)
        ->assertDontSee('@retire-'.$investment->id)
        ->assertNoJavascriptErrors();
});

it('reads the net worth on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/net-worth')
        ->on()->mobile()
        ->assertSee('Net worth')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
