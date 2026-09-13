<?php

declare(strict_types=1);

use App\Actions\CapturePlanBaseline;
use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\MonthStatus;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

function forecastPlan(FinancialYear $year, User $user, string $categoryName, int $cents, string $type = 'expense'): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'type' => $type,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function forecastRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

it('reports where the year is heading', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Salary', 200_000, 'income');
    forecastPlan($year, $user, 'Housing', 70_000);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->component('reports/forecast')
            ->where('year', 2027)
            ->where('summary.incomeCents', 2_400_000)
            ->where('summary.expenseCents', 840_000)
            ->where('summary.savingsCents', 1_560_000));
});

it('says what each month forecast is based on', function (): void {
    [$user, $year] = userWithYear();

    forecastRecord($user, 'Housing', '2027-02-05', 1_000);
    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.source', 'Actual')
            ->where('months.0.status', MonthStatus::Complete->value)
            // Something recorded, but the plan still stands for the rest of the month.
            ->where('months.1.source', 'Plan + actual')
            ->where('months.2.source', 'Plan'));
});

it('forecasts a signed-off month from what really happened', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Housing', 70_000);
    forecastRecord($user, 'Housing', '2027-01-05', 50_000);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    // Complete means final: the 700,00 plan no longer has a say in January.
    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.source', 'Actual')
            ->where('months.0.expenseCents', 50_000)
            ->where('months.1.expenseCents', 70_000));
});

it('points out a month behind us that was never signed off', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    forecastRecord($user, 'Housing', '2026-03-05', 1_000);
    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2026]))
        ->assertInertia(fn ($page) => $page
            // January is signed off, so it is fine.
            ->where('months.0.needsAttention', false)
            // February and March are behind us and are not.
            ->where('months.1.needsAttention', true)
            ->where('months.2.needsAttention', true)
            // May is the month being lived through, so there is nothing to chase yet.
            ->where('months.4.needsAttention', false)
            ->where('months.5.needsAttention', false));
});

it('chases nothing in a year that has not started', function (): void {
    [$user] = userWithYear();

    // 2027 is ahead of the frozen clock.
    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.needsAttention', false)
            ->where('months.11.needsAttention', false));
});

it('chases every unfinished month of a year behind us', function (): void {
    [$user, $year] = userWithYear(2025);

    $this->travelTo('2026-05-10');

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2025]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.needsAttention', false)
            ->where('months.11.needsAttention', true));
});

it('carries the balance through the forecast months', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Salary', 100_000, 'income');

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.closingCents', 100_000)
            ->where('months.1.closingCents', 200_000)
            ->where('summary.yearEndCents', 1_200_000));
});

it('measures drift against the plan the year started with', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Housing', 70_000);

    resolve(CapturePlanBaseline::class)->handle($year->refresh());

    $year->planItems()->delete();

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.hasBaseline', true)
            ->where('summary.deviationCents', 840_000)
            ->whereNot('summary.baselineCapturedAt', null));
});

it('falls back to the current plan when no baseline was captured', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Housing', 70_000);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.hasBaseline', false)
            ->where('summary.deviationCents', 0)
            ->where('summary.baselineCapturedAt', null));
});

it('compares plan and forecast for each category', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Housing', 70_000);
    forecastPlan($year, $user, 'Utilities', 20_000);

    // Housing overspends by 200,00 in January, which the forecast carries.
    forecastRecord($user, 'Housing', '2027-01-05', 90_000);

    $this->actingAs($user)
        ->assertAuthenticated()
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(function ($page): void {
            $expenses = collect($page->toArray()['props']['categories']['expenses']);

            $housing = $expenses->firstWhere('categoryName', 'Housing');

            expect($housing['plannedCents'])->toBe(840_000)
                ->and($housing['forecastCents'])->toBe(860_000)
                ->and($housing['differenceCents'])->toBe(20_000)
                // The category furthest from its plan is listed first.
                ->and($expenses->first()['categoryName'])->toBe('Housing');
        });
});

it('leaves out categories with nothing planned and nothing expected', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Housing', 70_000);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(function ($page): void {
            $names = collect($page->toArray()['props']['categories']['expenses'])
                ->pluck('categoryName');

            expect($names)->toContain('Housing')
                ->and($names)->not->toContain('Holidays');
        });
});

it('keeps income and expense categories apart', function (): void {
    [$user, $year] = userWithYear();

    forecastPlan($year, $user, 'Salary', 200_000, 'income');
    forecastPlan($year, $user, 'Housing', 70_000);

    $this->actingAs($user)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertInertia(function ($page): void {
            $categories = $page->toArray()['props']['categories'];

            expect(collect($categories['income'])->pluck('categoryName'))->toContain('Salary')
                ->and(collect($categories['expenses'])->pluck('categoryName'))->toContain('Housing')
                ->and(collect($categories['income'])->pluck('categoryName'))->not->toContain('Housing');
        });
});

it('reports another user year as missing', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $this->actingAs($intruder)
        ->get(route('forecast.index', ['year' => 2027]))
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2027)->exists())->toBeTrue();
});

it('lives under the reports hub and redirects from where it used to be', function (): void {
    [$user] = userWithYear();

    expect(route('forecast.index', ['year' => 2027], absolute: false))
        ->toBe('/years/2027/reports/forecast');

    $this->actingAs($user)
        ->get('/years/2027/forecast')
        ->assertRedirect(route('forecast.index', ['year' => 2027]));
});
