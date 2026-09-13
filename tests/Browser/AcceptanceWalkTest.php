<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

/*
 * §2.2: what v1 has to let someone do, walked end to end over the demo dataset — the same
 * year a person would see on a fresh install (§12.2).
 *
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::query()
        ->where('email', DatabaseSeeder::ADMIN_EMAIL)
        ->sole();
});

it('walks the acceptance criteria over the demo year', function (): void {
    $year = DemoSeeder::YEAR;

    $page = $this->actingAs($this->user)->visit('/dashboard?year='.$year);

    // "See Actuals per month and per category automatically."
    $page->assertSee('Available now')
        ->assertSee('Income so far')
        ->assertNoJavascriptErrors();

    // "Plan monthly and one-off income, and monthly and annual expenses."
    $page->navigate('/years/'.$year.'/plan/income')
        ->assertSee('Salary')
        ->navigate('/years/'.$year.'/plan/expenses')
        ->assertSee('Housing')
        ->navigate('/years/'.$year.'/plan/irregular')
        ->assertSee('Car insurance')
        ->assertNoJavascriptErrors();

    // "Manually record every real transaction, with category and subcategory."
    $page->navigate('/transactions?year='.$year)
        ->assertSee('Supermarket')
        ->assertNoJavascriptErrors();

    // "Compare Plan and Actual, and see an updated Forecast."
    $page->navigate('/years/'.$year.'/reports/comparison')
        ->assertSee('Plan vs actual')
        ->navigate('/years/'.$year.'/reports/forecast')
        ->assertSee('Forecast')
        ->assertNoJavascriptErrors();

    // "Complete and reopen a month." January was signed off by the seeder.
    $page->navigate('/years/'.$year.'/months')
        ->assertSee('Complete')
        ->assertNoJavascriptErrors();

    // "Track cash flow and balance, emergency fund, and net worth."
    $page->navigate('/years/'.$year.'/reports/cash-flow')
        ->assertSee('Cash flow')
        ->navigate('/goals/emergency-fund')
        ->assertSee('Emergency fund')
        ->navigate('/goals/net-worth')
        ->assertSee('Net worth')
        ->assertNoJavascriptErrors();

    // "Find incorrect or incomplete transactions." The seeder leaves exactly one.
    $page->navigate('/transactions?year='.$year.'&issues=1')
        ->assertSee('Miscategorised receipt')
        ->assertNoJavascriptErrors();
});

it('walks the same criteria on a phone', function (): void {
    $year = DemoSeeder::YEAR;

    $page = $this->actingAs($this->user)
        ->visit('/dashboard?year='.$year)
        ->on()->mobile();

    foreach ([
        '/dashboard?year='.$year,
        '/transactions?year='.$year,
        '/years/'.$year.'/months',
        '/years/'.$year.'/reports/comparison',
        '/years/'.$year.'/reports/cash-flow',
        '/years/'.$year.'/reports/forecast',
        '/goals/net-worth',
        '/goals',
        '/subscriptions',
    ] as $path) {
        $page->navigate($path)
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    }
});

it('reopens a finished month and closes it again', function (): void {
    $year = DemoSeeder::YEAR;

    // MON-04, MON-05: a month the user signed off can be put back into progress and
    // finished again, which is what makes a correction possible after the fact.
    $page = $this->actingAs($this->user)->visit('/years/'.$year.'/months/1');

    $page->assertSee('Complete')
        ->click('@reopen-month')
        ->assertSee('In progress')
        ->assertNoJavascriptErrors();

    $page->click('@complete-month')
        ->assertSee('Complete')
        ->assertNoJavascriptErrors();
});

/**
 * Every authenticated page, over a year with real figures in it.
 *
 * @return list<array{0: string}>
 */
function everyAuthenticatedPage(): array
{
    $year = DemoSeeder::YEAR;

    $paths = [
        '/dashboard',
        '/transactions',
        '/goals',
        '/goals/emergency-fund',
        '/goals/net-worth',
        '/subscriptions',
        '/years/create',
        '/settings/profile',
        '/settings/password',
        '/settings/appearance',
        '/settings/two-factor',
        '/settings/preferences',
        '/settings/accounts',
        '/settings/categories',
        '/settings/invitations',
        '/years/'.$year.'/months',
        '/years/'.$year.'/months/1',
        '/years/'.$year.'/reports/comparison',
        '/years/'.$year.'/reports/cash-flow',
        '/years/'.$year.'/reports/forecast',
    ];

    foreach (['income', 'expenses', 'irregular', 'opening'] as $tab) {
        $paths[] = '/years/'.$year.'/plan/'.$tab;
    }

    foreach (['opening', 'income', 'expenses', 'irregular', 'goals', 'review'] as $step) {
        $paths[] = '/years/'.$year.'/setup/'.$step;
    }

    return array_map(fn (string $path): array => [$path], $paths);
}

it('renders every authenticated page at 1280px without errors', function (string $path): void {
    // TST-04. A page that throws on a year with real figures in it is a page nobody can
    // use, however well its feature test passes.
    $this->actingAs($this->user)
        ->visit($path)
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with(everyAuthenticatedPage(...));

it('renders every authenticated page at 375px without errors', function (string $path): void {
    $this->actingAs($this->user)
        ->visit($path)
        ->on()->mobile()
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with(everyAuthenticatedPage(...));
