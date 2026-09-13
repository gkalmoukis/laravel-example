<?php

declare(strict_types=1);

use App\Enums\NetWorthItemKind;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;

/*
 * The month close flow end to end (TST-04, UX-10). Browser tests assert through the
 * interface: the application runs in a separate process, so a model re-read here would
 * return a stale snapshot.
 *
 * These run against a month that is genuinely behind the user, so completing it is
 * allowed without the early-completion confirmation.
 */

function closedMonth(): int
{
    // The month before this one, which is over and so can be finished outright.
    return (int) date('n', (int) mktime(0, 0, 0, (int) date('n') - 1, 1));
}

function closedMonthYear(): int
{
    return (int) date('Y', (int) mktime(0, 0, 0, (int) date('n') - 1, 1));
}

function monthPath(string $suffix = ''): string
{
    return '/years/'.closedMonthYear().'/months'.$suffix;
}

function dateInClosedMonth(int $day = 5): string
{
    return sprintf('%d-%02d-%02d', closedMonthYear(), closedMonth(), $day);
}

function monthsUser(): User
{
    return userWithYear(closedMonthYear())[0];
}

function monthsYear(User $user): FinancialYear
{
    return $user->financialYears()->where('year', closedMonthYear())->firstOrFail();
}

function spendInMonth(User $user, string $categoryName, int $cents, int $day = 5): Transaction
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    return Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => dateInClosedMonth($day),
        'amount_cents' => $cents,
        'description' => $categoryName.' bill',
    ]);
}

it('walks from the year overview into a month and finishes it', function (): void {
    $user = monthsUser();

    spendInMonth($user, 'Housing', 45_000);

    $page = $this->actingAs($user)->visit(monthPath());

    // The year overview says where each month stands.
    $page->assertSee('month by month')
        ->assertSee('In progress')
        ->assertNoJavascriptErrors();

    // Into the month itself, in the order someone closing it works through.
    $page->navigate(monthPath('/'.closedMonth()))
        ->assertSee('Totals')
        ->assertSee('450,00')
        ->assertSee('Anything to fix')
        ->assertSee('Where it went against the plan')
        ->assertNoJavascriptErrors();

    // Nothing is flagged, so it can be finished.
    $page->click('@complete-month')
        ->assertSee('is complete')
        ->assertNoJavascriptErrors();
});

it('refuses to finish a month with something still to fix, then allows it once fixed', function (): void {
    $user = monthsUser();

    // Filed under a category that records the opposite direction (TXV-03).
    $wrong = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Salary')->firstOrFail()->id,
        'occurred_on' => dateInClosedMonth(),
        'amount_cents' => 5_000,
        'description' => 'Misfiled thing',
    ]);

    $page = $this->actingAs($user)->visit(monthPath('/'.closedMonth()));

    $page->assertSee('Misfiled thing')
        ->assertSee('Its category records money moving the other way')
        ->click('@complete-month')
        ->assertSee('Fix 1 transaction first')
        ->assertNoJavascriptErrors();

    // Put it right, and the month can be finished.
    $housing = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();
    $wrong->forceFill(['category_id' => $housing->id])->save();

    $this->actingAs($user)
        ->visit(monthPath('/'.closedMonth()))
        ->assertDontSee('Its category records money moving the other way')
        ->click('@complete-month')
        ->assertSee('is complete')
        ->assertNoJavascriptErrors();
});

it('reconciles what the user has against the transactions', function (): void {
    $user = monthsUser();
    $year = monthsYear($user);

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $cash->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 100_000]);

    spendInMonth($user, 'Housing', 30_000);

    $page = $this->actingAs($user)->visit(monthPath('/'.closedMonth()));

    // The transactions say 700,00 is left; typing a different figure says by how much.
    //
    // Saving is asserted in tests/Feature/Months/NetWorthSnapshotTest.php rather than
    // here: the submit button does not dispatch its request under Playwright, which is
    // recorded as a known gap in .claude/backlog.md.
    $page->assertSee('Your transactions add up to')
        ->assertSee('700,00')
        ->fill('#holding-'.$cash->id, '750,00')
        ->assertSee('higher')
        ->assertNoJavascriptErrors();
});

it('reopens a finished month', function (): void {
    $user = monthsUser();
    $year = monthsYear($user);

    MonthClosure::factory()->for($year)->create([
        'month' => closedMonth(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->visit(monthPath('/'.closedMonth()))
        ->assertSee('is complete')
        ->click('@reopen-month')
        ->assertSee('Finished with')
        ->assertNoJavascriptErrors();
});

it('closes a month on a phone', function (): void {
    $user = monthsUser();

    spendInMonth($user, 'Housing', 45_000);

    $this->actingAs($user)
        ->visit(monthPath('/'.closedMonth()))
        ->on()->mobile()
        ->assertSee('Totals')
        ->click('@complete-month')
        ->assertSee('is complete')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('reads the year overview on a phone', function (): void {
    $user = monthsUser();

    $this->actingAs($user)
        ->visit(monthPath())
        ->on()->mobile()
        ->assertSee('month by month')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
