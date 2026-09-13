<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Models\NetWorthSnapshot;
use App\ValueObjects\Money;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('sends a new account to make a plan before showing it six empty cards', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->visit('/dashboard')
        ->assertPathIs('/years/create')
        ->assertSee('Start a plan')
        ->assertNoJavascriptErrors();
});

it('shows the six figures and the charts behind them', function (): void {
    $year = (int) date('Y');
    [$user, $financialYear] = userWithYear($year);

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()->updateOrCreate(
        ['financial_year_id' => $financialYear->id, 'net_worth_item_id' => $cash->id, 'month' => 0],
        ['value_cents' => Money::fromCents(500_000)],
    );

    resolve(CreatePlanItem::class)->handle($financialYear, [
        'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    $this->actingAs($user)
        ->visit('/dashboard')
        // DASH-02, in the order the specification sets.
        ->assertSee('Available now')
        ->assertSee('Forecast year end')
        ->assertSee('Income so far')
        ->assertSee('Expenses so far')
        ->assertSee('Saved so far')
        ->assertSee('Emergency fund')
        // DASH-03: these are rows inside the cards they belong to now, not a pill row.
        ->assertSee('Net worth')
        ->assertSee('Months finished')
        // DASH-04: each chart arrives behind its own skeleton. Two lead; the other
        // three are one click away rather than in front of a screen already carrying
        // six cards (UX-09).
        ->assertSee('Closing balance')
        ->assertSee('Month by month')
        ->click('@more-charts')
        ->assertSee('Where the money went')
        ->assertSee('Net worth')
        ->assertSee('Goals')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('links every card to the screen that explains it', function (): void {
    $year = (int) date('Y');
    [$user] = userWithYear($year);

    // DASH-06. The whole card is the link, so the target is a comfortable size on a phone.
    $this->actingAs($user)
        ->visit('/dashboard')
        ->click('@card-available')
        ->assertPathIs('/years/'.$year.'/reports/cash-flow')
        ->assertNoJavascriptErrors();

    $this->actingAs($user)
        ->visit('/dashboard')
        ->click('@card-year-end')
        ->assertPathIs('/years/'.$year.'/reports/forecast')
        ->assertNoJavascriptErrors();

    $this->actingAs($user)
        ->visit('/dashboard')
        ->click('@card-emergency-fund')
        ->assertPathIs('/goals/emergency-fund')
        ->assertNoJavascriptErrors();
});

it('keeps six cards above the fold and folds the rest away', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // UX-09: six metric cards and no more. The three figures that used to sit in a pill
    // row beneath them are now rows inside the cards they belong to, and three of the
    // five charts are behind a disclosure.
    $this->actingAs($user)
        ->visit('/dashboard')
        ->assertCount('@metric-card', 6)
        ->assertSee('Months finished')
        ->assertSee('Net worth')
        ->assertDontSee('Where the money went')
        ->click('@more-charts')
        ->assertSee('Where the money went')
        ->assertNoJavascriptErrors();
});

it('reads the dashboard on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/dashboard')
        ->on()->mobile()
        ->assertSee('Available now')
        ->assertSee('Emergency fund')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
