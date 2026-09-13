<?php

declare(strict_types=1);

use App\Models\SalaryModel;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('walks through setting up a year from nothing', function (): void {
    $user = planningUser();

    $page = $this->actingAs($user)->visit('/years/create');

    $page->assertSee('Start a plan')
        ->click('@create-year-button')
        ->assertPathBeginsWith('/years/')
        ->assertSee('Opening position')
        ->assertNoJavascriptErrors();

    // Step one: type what you have. The field name carries brackets, so it needs an
    // attribute selector rather than a bare name.
    $page->fill('input[name="holdings[0][amount]"]', '5.000,00')
        ->click('@save-opening-button')
        ->assertSee('Money you can spend')
        ->assertNoJavascriptErrors();

    // Saving keeps the user on the step; moving on is a separate, deliberate click.
    $page->click('@next-step-button')
        ->assertPathIs('/years/'.date('Y').'/setup/income')
        ->assertNoJavascriptErrors();

    // Step two: the salary, which generates the year's income on its own (INC-01).
    $page->fill('base_amount', '1.800,00')
        ->click('@save-salary-button')
        ->assertSee('Salary')
        ->assertNoJavascriptErrors();

    // One step left, and it is optional, so the wizard can be walked to its end.
    $page->navigate('/years/'.date('Y').'/setup/expenses')
        ->assertPathIs('/years/'.date('Y').'/setup/expenses')
        ->assertNoJavascriptErrors();

    $page->click('@finish-setup-button')
        ->assertPathBeginsWith('/years/')
        ->assertDontSee('Setup unfinished')
        ->assertNoJavascriptErrors();
});

it('walks the setup wizard end to end on a phone', function (): void {
    $user = planningUser();

    // TST-04: the same walk at 375 px, where the wizard has the least room to work in.
    $page = $this->actingAs($user)->visit('/years/create')->on()->mobile();

    $page->click('@create-year-button')
        ->assertSee('Opening position')
        ->fill('input[name="holdings[0][amount]"]', '2.500,00')
        ->click('@save-opening-button')
        ->assertSee('Money you can spend')
        ->click('@next-step-button')
        ->assertPathIs('/years/'.date('Y').'/setup/income')
        ->fill('base_amount', '1.500,00')
        ->click('@save-salary-button')
        ->assertSee('Salary')
        ->navigate('/years/'.date('Y').'/setup/expenses')
        ->click('@finish-setup-button')
        ->assertPathBeginsWith('/years/')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('generates the fourteen payments from a salary', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->visit('/years/2027/setup/income')
        ->fill('base_amount', '1.800,00')
        ->click('@save-salary-button')
        ->assertSee('Christmas Bonus')
        ->assertSee('Easter Bonus')
        ->assertSee('Vacation Allowance')
        ->assertNoJavascriptErrors();
});

it('adds a monthly cost to the budget', function (): void {
    [$user] = userWithYear();

    $housing = $user->categories()->where('name', 'Housing')->firstOrFail();

    $this->actingAs($user)
        ->visit('/years/2027/setup/expenses')
        ->fill('plan-item-name', 'Rent')
        ->fill('plan-item-amount', '700,00')
        // The category picker is a composed listbox in a portal rather than a native
        // select, so it is opened and its option clicked by role.
        ->click('#plan-item-category')
        ->click('[role="option"]:has-text("'.$housing->name.'")')
        ->click('@save-plan-item')
        ->assertSee('Rent')
        ->assertNoJavascriptErrors();
});

it('finishes setup from the last step and lands on the plan', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)->patch(route('salary-model.update', ['year' => 2027]), [
        'base_amount' => '1.800,00',
        'payments' => SalaryModel::defaultPayments(),
    ]);

    // The review step is gone: the plan's own screens show what was frozen, and the
    // last step of the wizard is where setup is finished (YEAR-04, YEAR-05).
    $this->actingAs($user)
        ->visit('/years/2027/setup/expenses')
        ->click('@finish-setup-button')
        ->assertPathBeginsWith('/years/2027/plan')
        // Reaching the plan without the unfinished-setup reminder is what proves it.
        ->assertDontSee('Setup unfinished')
        ->assertNoJavascriptErrors();
});

it('shows the budget grid', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->visit('/years/2027/plan/expenses')
        ->assertSee('Monthly budget')
        ->assertSee('Housing')
        ->assertNoJavascriptErrors();
});

it('renders the plan on a phone without errors', function (string $path): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->visit($path)
        ->on()->mobile()
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with([
    '/years/create',
    '/years/2027/setup/opening',
    '/years/2027/setup/income',
    '/years/2027/setup/expenses',
    '/years/2027/plan/income',
    '/years/2027/plan/expenses',
    '/years/2027/plan/irregular',
    '/years/2027/plan/opening',
]);

it('reminds the user when a year was never finished', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->visit('/years/2027/plan/income')
        ->assertSee('Setup unfinished')
        ->assertSee('Finish setting up 2027')
        ->assertNoJavascriptErrors();
});

it('adds, changes and removes an irregular cost from the plan tab', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $year = date('Y');

    // Until now the wizard was the only place a plan item could be created, so a year
    // could be planned once and never corrected (IRR-01, IRR-02).
    $page = $this->actingAs($user)->visit('/years/'.$year.'/plan/irregular');

    $page->click('@add-irregular')
        ->fill('plan-item-name', 'Summer holiday')
        ->click('#plan-item-category')
        ->click('[role="option"]:has-text("Housing")')
        ->fill('plan-item-amount', '1.200,00')
        ->click('@save-plan-item')
        ->assertSee('Summer holiday')
        ->assertSee('1.200,00')
        ->assertNoJavascriptErrors();

    $page->click('[aria-label="Edit Summer holiday"]')
        ->fill('plan-item-name', 'Winter holiday')
        ->click('@save-plan-item')
        ->assertSee('Winter holiday')
        ->assertNoJavascriptErrors();

    $page->click('[aria-label="Remove Winter holiday"]')
        ->assertSee('Remove Winter holiday?')
        ->click('@confirm-remove-plan-item')
        ->assertDontSee('Winter holiday')
        ->assertNoJavascriptErrors();
});

it('adds an income line from the plan tab', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/plan/income')
        ->click('@add-income')
        ->fill('plan-item-name', 'Freelance')
        ->click('#plan-item-category')
        ->click('[role="option"]:has-text("Salary")')
        ->fill('plan-item-amount', '400,00')
        ->click('@save-plan-item')
        ->assertSee('Freelance')
        ->assertNoJavascriptErrors();
});

it('folds the rarely needed fields away until asked', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // Four fields carry the common case; the rest is one click away (UX-07).
    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/plan/irregular')
        ->click('@add-irregular')
        ->assertDontSee('Day of the month it is paid')
        ->click('@plan-item-more')
        ->assertSee('Day of the month it is paid')
        ->assertSee('Set money aside for it every month')
        ->assertNoJavascriptErrors();
});

it('plans an irregular cost on a phone', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/years/'.date('Y').'/plan/irregular')
        ->on()->mobile()
        ->click('@add-irregular')
        ->fill('plan-item-name', 'New boiler')
        ->click('#plan-item-category')
        ->click('[role="option"]:has-text("Housing")')
        ->fill('plan-item-amount', '850,00')
        ->click('@save-plan-item')
        ->assertSee('New boiler')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('says that a budget cell saved, and offers it back', function (): void {
    [$user] = userWithYear(2027);

    // The grid wrote on blur and said nothing at all, so there was no way to tell a
    // saved number from a typo (BUD-02).
    $page = $this->actingAs($user)->visit('/years/2027/plan/expenses');

    // Enter commits without leaving the page; clicking the tab would navigate.
    $page->fill('@cell-housing-mar', '450,00')
        ->keys('@cell-housing-mar', 'Enter')
        ->assertSee('Undo')
        ->assertNoJavascriptErrors();

    // The number is really there on a fresh load, not just on screen.
    $this->actingAs($user)
        ->visit('/years/2027/plan/expenses')
        ->assertSee('450,00')
        ->assertNoJavascriptErrors();
});

it('puts a budget cell back when the write is undone', function (): void {
    [$user] = userWithYear(2027);

    $page = $this->actingAs($user)->visit('/years/2027/plan/expenses');

    $page->fill('@cell-housing-mar', '450,00')
        ->keys('@cell-housing-mar', 'Enter')
        ->assertSee('Undo')
        ->click('@undo-budget-cell')
        ->assertNoJavascriptErrors();

    $this->actingAs($user)
        ->visit('/years/2027/plan/expenses')
        ->assertDontSee('450,00')
        ->assertNoJavascriptErrors();
});

it('surfaces a budget cell the server refuses', function (): void {
    [$user] = userWithYear(2027);

    // An amount that cannot be parsed is the refusal a user can actually provoke; the
    // ambiguous-category path takes the input away entirely instead (BUD-03, EDGE-03).
    $this->actingAs($user)
        ->visit('/years/2027/plan/expenses')
        ->fill('@cell-housing-mar', 'lots')
        ->keys('@cell-housing-mar', 'Enter')
        ->assertSee('Enter an amount like')
        ->assertNoJavascriptErrors();
});

it('edits the budget one month at a time on a phone', function (): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/years/2027/plan/expenses')
        ->on()->mobile()
        ->fill('@cell-housing-jan', '300,00')
        ->keys('@cell-housing-jan', 'Enter')
        ->assertSee('Undo')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('shows what the plan adds up to from every tab', function (string $tab): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/years/2027/plan/'.$tab)
        ->assertSee('Planned income')
        ->assertSee('Balance at the end of the year')
        ->assertNoJavascriptErrors();
})->with(['income', 'expenses', 'irregular', 'opening']);

it('names the next thing the plan is missing', function (): void {
    [$user] = userWithYear(2027);

    // A plan with nothing in it says what to do rather than showing four zeroes and
    // leaving the user to work it out (UX-05, EDGE-02).
    $this->actingAs($user)
        ->visit('/years/2027/plan/income')
        ->assertSee('Nothing in income yet')
        ->click('@plan-next-step')
        ->assertPathIs('/years/2027/plan/income')
        ->assertNoJavascriptErrors();
});

it('reads the plan totals on a phone', function (): void {
    [$user] = userWithYear(2027);

    $this->actingAs($user)
        ->visit('/years/2027/plan/expenses')
        ->on()->mobile()
        ->assertSee('Planned income')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
