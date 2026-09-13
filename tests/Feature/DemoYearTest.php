<?php

declare(strict_types=1);

use App\Actions\CalculateMonthlyFigures;
use App\Enums\MonthStatus;
use App\Enums\PlanItemSource;
use App\Models\Invitation;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use Tests\Fixtures\DemoYear;

/*
 * The demo dataset is the browser-test fixture, so its shape is asserted rather than
 * assumed: a fixture that quietly stopped producing half of it would take the tests that
 * depend on it down with it.
 */

beforeEach(function (): void {
    [$this->user, $this->year] = DemoYear::build();
});

it('builds a year to look at', function (): void {
    expect($this->year->year)->toBe(2027);
});

it('records about sixty transactions a month for the first half of the year', function (): void {
    for ($month = 1; $month <= 6; $month++) {
        $count = $this->user->transactions()
            ->whereYear('occurred_on', DemoYear::YEAR)
            ->whereMonth('occurred_on', $month)
            ->count();

        expect($count)->toBeGreaterThanOrEqual(55)->toBeLessThanOrEqual(65);
    }

    // Nothing after June, so half the year is recorded and half is still forecast.
    expect($this->user->transactions()->whereMonth('occurred_on', 7)->count())->toBe(0);
});

it('signs off January to April and leaves May and June under way', function (): void {
    expect(MonthClosure::query()->where('financial_year_id', $this->year->id)->count())->toBe(4);

    foreach ([1, 2, 3, 4] as $month) {
        expect(MonthClosure::isMonthComplete($this->year->user_id, DemoYear::YEAR, $month))->toBeTrue();
    }

    foreach ([5, 6] as $month) {
        expect(MonthClosure::isMonthComplete($this->year->user_id, DemoYear::YEAR, $month))->toBeFalse();
    }
});

it('leaves exactly one transaction needing a look, in June', function (): void {
    $flagged = Transaction::flagged()
        ->where('transactions.user_id', $this->user->id)
        ->get();

    expect($flagged)->toHaveCount(1)
        ->and($flagged->firstOrFail()->occurred_on->month)->toBe(6);
});

it('plans the year: a salary, a budget, three irregular costs and three subscriptions', function (): void {
    expect($this->year->salaryModel()->exists())->toBeTrue()
        ->and($this->year->planItems()->where('source', PlanItemSource::SalaryModel)->count())->toBe(4)
        ->and($this->year->planItems()->where('source', PlanItemSource::Subscription)->count())->toBe(3)
        ->and($this->year->planItems()->where('kind', 'irregular')->count())->toBe(3)
        // One of the irregular items is set aside for monthly rather than paid in one go.
        ->and($this->year->planItems()->where('allocation', 'spread')->count())->toBe(1)
        ->and($this->user->subscriptions()->count())->toBe(3);
});

it('opens the year and records net worth for the first four months', function (): void {
    $months = NetWorthSnapshot::query()
        ->where('financial_year_id', $this->year->id)
        ->distinct()
        ->pluck('month')
        ->sort()
        ->values()
        ->all();

    expect($months)->toBe([0, 1, 2, 3, 4]);
});

it('gives the user goals beyond the emergency fund they start with', function (): void {
    // The emergency fund is provisioned with the account, so the two built ones join it.
    expect($this->user->goals()->count())->toBe(3);
});

it('gives the admin invitations in every state the screen can render', function (): void {
    expect($this->user->is_admin)->toBeTrue()
        ->and(Invitation::query()->where('invited_by', $this->user->id)->count())->toBe(3);
});

it('produces a year whose months read as the specification describes', function (): void {
    $figures = resolve(CalculateMonthlyFigures::class)->handle($this->year);

    foreach ([1, 2, 3, 4] as $month) {
        expect($figures->month($month)->status)->toBe(MonthStatus::Complete);
    }

    foreach ([5, 6] as $month) {
        expect($figures->month($month)->status)->toBe(MonthStatus::InProgress);
    }

    expect($figures->month(7)->status)->toBe(MonthStatus::NotStarted);
});
