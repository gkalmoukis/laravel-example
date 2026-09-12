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
        ->fill('name', 'Rent')
        ->fill('amount', '700,00')
        // The category picker is a composed listbox in a portal rather than a native
        // select, so it is opened and its option clicked by role.
        ->click('#category_id')
        ->click('[role="option"]:has-text("'.$housing->name.'")')
        ->click('@add-plan-item-button')
        ->assertSee('Rent')
        ->assertNoJavascriptErrors();
});

it('shows what the plan adds up to before finishing', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)->patch(route('salary-model.update', ['year' => 2027]), [
        'base_amount' => '1.800,00',
        'payments' => SalaryModel::defaultPayments(),
    ]);

    $this->actingAs($user)
        ->visit('/years/2027/setup/review')
        ->assertSee('Planned income')
        ->assertSee('Balance at the end of the year')
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
    '/years/2027/setup/review',
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
