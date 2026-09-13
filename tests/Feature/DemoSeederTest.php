<?php

declare(strict_types=1);

use App\Actions\CalculateMonthlyFigures;
use App\Enums\MonthStatus;
use App\Enums\PlanItemSource;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

/*
 * The demo dataset is also the browser-test fixture, so its shape is asserted rather than
 * assumed: §12.2 describes it, and a seeder that quietly stopped producing half of it
 * would take the tests that depend on it down with it.
 */

function demoUser(): User
{
    return User::query()->where('email', DatabaseSeeder::ADMIN_EMAIL)->sole();
}

function demoYear(): FinancialYear
{
    return demoUser()->financialYears()->where('year', DemoSeeder::YEAR)->sole();
}

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('seeds the local admin a year to look at', function (): void {
    expect(demoYear()->year)->toBe(2027);
});

it('records about sixty transactions a month for the first half of the year', function (): void {
    $user = demoUser();

    for ($month = 1; $month <= 6; $month++) {
        $count = $user->transactions()
            ->whereYear('occurred_on', DemoSeeder::YEAR)
            ->whereMonth('occurred_on', $month)
            ->count();

        expect($count)->toBeGreaterThanOrEqual(55)->toBeLessThanOrEqual(65);
    }

    // Nothing after June, so half the year is recorded and half is still forecast.
    expect($user->transactions()->whereMonth('occurred_on', 7)->count())->toBe(0);
});

it('signs off January to April and leaves May and June under way', function (): void {
    $year = demoYear();

    expect(MonthClosure::query()->where('financial_year_id', $year->id)->count())->toBe(4);

    foreach ([1, 2, 3, 4] as $month) {
        expect(MonthClosure::isMonthComplete($year->user_id, DemoSeeder::YEAR, $month))->toBeTrue();
    }

    foreach ([5, 6] as $month) {
        expect(MonthClosure::isMonthComplete($year->user_id, DemoSeeder::YEAR, $month))->toBeFalse();
    }
});

it('leaves exactly one transaction needing a look, in June', function (): void {
    $flagged = Transaction::flagged()
        ->where('transactions.user_id', demoUser()->id)
        ->get();

    expect($flagged)->toHaveCount(1)
        ->and($flagged->firstOrFail()->occurred_on->month)->toBe(6);
});

it('plans the year: a salary, a budget, three irregular costs and three subscriptions', function (): void {
    $year = demoYear();

    expect($year->salaryModel()->exists())->toBeTrue()
        ->and($year->planItems()->where('source', PlanItemSource::SalaryModel)->count())->toBe(4)
        ->and($year->planItems()->where('source', PlanItemSource::Subscription)->count())->toBe(3)
        ->and($year->planItems()->where('kind', 'irregular')->count())->toBe(3)
        // One of the irregular items is set aside for monthly rather than paid in one go.
        ->and($year->planItems()->where('allocation', 'spread')->count())->toBe(1)
        ->and(demoUser()->subscriptions()->count())->toBe(3);
});

it('opens the year and records net worth for the first four months', function (): void {
    $year = demoYear();

    $months = NetWorthSnapshot::query()
        ->where('financial_year_id', $year->id)
        ->distinct()
        ->pluck('month')
        ->sort()
        ->values()
        ->all();

    expect($months)->toBe([0, 1, 2, 3, 4]);
});

it('gives the user goals beyond the emergency fund they start with', function (): void {
    // The emergency fund is provisioned with the account, so the two seeded ones join it.
    expect(demoUser()->goals()->count())->toBe(3);
});

it('produces a year whose months read as the specification describes', function (): void {
    $figures = resolve(CalculateMonthlyFigures::class)->handle(demoYear());

    foreach ([1, 2, 3, 4] as $month) {
        expect($figures->month($month)->status)->toBe(MonthStatus::Complete);
    }

    foreach ([5, 6] as $month) {
        expect($figures->month($month)->status)->toBe(MonthStatus::InProgress);
    }

    expect($figures->month(7)->status)->toBe(MonthStatus::NotStarted);
});

it('refuses to seed twice over the same year', function (): void {
    $before = demoUser()->transactions()->count();

    $this->seed(DemoSeeder::class);

    expect(demoUser()->transactions()->count())->toBe($before);
});
