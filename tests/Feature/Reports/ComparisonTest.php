<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\VarianceStatus;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

function comparisonPlan(FinancialYear $year, User $user, string $categoryName, int $cents): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function comparisonRecord(User $user, string $categoryName, string $date, int $cents, ?string $subcategoryName = null): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'subcategory_id' => $subcategoryName === null
            ? null
            : $user->categories()->where('name', $subcategoryName)->firstOrFail()->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

it('compares one month against its plan', function (): void {
    [$user, $year] = userWithYear();

    comparisonPlan($year, $user, 'Housing', 70_000);
    comparisonRecord($user, 'Housing', '2027-03-05', 80_000);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page): void {
            $page->component('reports/comparison')
                ->where('mode', 'month')
                ->where('month', 3);

            $housing = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Housing');

            expect($housing['plannedCents'])->toBe(70_000)
                ->and($housing['actualCents'])->toBe(80_000)
                ->and($housing['varianceCents'])->toBe(10_000);
        });
});

it('opens on the last month the user signed off', function (): void {
    [$user, $year] = userWithYear();

    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 5, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 7, 'completed_at' => null]);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page->where('month', 5));
});

it('opens on the month the user is living through when none is signed off', function (): void {
    [$user] = userWithYear(2026);

    $this->travelTo('2026-08-14');

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2026]))
        ->assertInertia(fn ($page) => $page->where('month', 8));
});

it('opens on January for a year the user is not living in', function (): void {
    [$user] = userWithYear();

    // 2027 is ahead of the frozen clock, so there is no current month to fall back on.
    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page->where('month', 1));
});

it('ignores a month that could not be one', function (string $month): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'month' => $month]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('month', 1));
})->with(['0', '13', 'March']);

it('sums only signed-off months in the year to date view', function (): void {
    [$user, $year] = userWithYear();

    comparisonPlan($year, $user, 'Housing', 70_000);

    comparisonRecord($user, 'Housing', '2027-01-05', 60_000);
    comparisonRecord($user, 'Housing', '2027-02-05', 80_000);
    comparisonRecord($user, 'Housing', '2027-03-05', 99_999);

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);
    MonthClosure::factory()->for($year)->create(['month' => 2, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'mode' => 'ytd']))
        ->assertInertia(function ($page): void {
            $page->where('mode', 'ytd')
                ->where('month', null)
                ->where('completedMonths', 2);

            $housing = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Housing');

            // March is left out of both sides, so a part month is never measured against
            // a whole one (CMP-04).
            expect($housing['plannedCents'])->toBe(140_000)
                ->and($housing['actualCents'])->toBe(140_000)
                ->and($housing['status'])->toBe(VarianceStatus::Ok->value);
        });
});

it('has nothing to compare before any month is signed off', function (): void {
    [$user, $year] = userWithYear();

    comparisonPlan($year, $user, 'Housing', 70_000);
    comparisonRecord($user, 'Housing', '2027-01-05', 60_000);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'mode' => 'ytd']))
        ->assertInertia(function ($page): void {
            $page->where('completedMonths', 0);

            $housing = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Housing');

            expect($housing['plannedCents'])->toBe(0)
                ->and($housing['actualCents'])->toBe(0)
                ->and($housing['subcategories'])->toBe([]);
        });
});

it('puts the worst categories first', function (): void {
    [$user, $year] = userWithYear();

    comparisonPlan($year, $user, 'Housing', 70_000);
    comparisonPlan($year, $user, 'Utilities', 20_000);

    comparisonRecord($user, 'Housing', '2027-03-05', 71_000);
    comparisonRecord($user, 'Utilities', '2027-03-05', 60_000);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'month' => 3]))
        ->assertInertia(fn ($page) => $page
            ->where('expenses.0.categoryName', 'Utilities')
            ->where('expenses.0.label', 'Over budget')
            ->where('expenses.1.categoryName', 'Housing'));
});

it('breaks a month down by subcategory', function (): void {
    [$user] = userWithYear();

    comparisonRecord($user, 'Food & Groceries', '2027-03-05', 3_000, 'Supermarket');
    comparisonRecord($user, 'Food & Groceries', '2027-03-06', 2_000);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page): void {
            $food = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Food & Groceries');

            expect(collect($food['subcategories'])->pluck('name'))
                ->toContain('Supermarket')
                ->toContain('No subcategory');
        });
});

it('breaks the year to date down over the signed-off months only', function (): void {
    [$user, $year] = userWithYear();

    comparisonRecord($user, 'Food & Groceries', '2027-01-05', 3_000, 'Supermarket');
    comparisonRecord($user, 'Food & Groceries', '2027-03-05', 9_999, 'Supermarket');

    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'mode' => 'ytd']))
        ->assertInertia(function ($page): void {
            $food = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Food & Groceries');

            $supermarket = collect($food['subcategories'])->firstWhere('name', 'Supermarket');

            // The parts have to add up to the whole above them, so March is out of both.
            expect($supermarket['actualCents'])->toBe(3_000)
                ->and($food['actualCents'])->toBe(3_000);
        });
});

it('marks a category funded by setting money aside', function (): void {
    [$user, $year] = userWithYear();

    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Holidays')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Summer holiday',
        'kind' => PlanItemKind::Irregular,
        'frequency' => Frequency::Annual,
        'start_month' => 8,
        'allocation' => Allocation::Spread,
    ], Money::fromCents(120_000));

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page): void {
            $holidays = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Holidays');

            // Judged on the year so far: three months of setting aside 100,00 € (CMP-05).
            expect($holidays['isSpread'])->toBeTrue()
                ->and($holidays['plannedCents'])->toBe(30_000);
        });
});

it('keeps income and expenses apart', function (): void {
    [$user, $year] = userWithYear();

    comparisonPlan($year, $user, 'Housing', 70_000);
    comparisonRecord($user, 'Salary', '2027-03-25', 200_000);

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page): void {
            $props = $page->toArray()['props'];

            expect(collect($props['expenses'])->pluck('categoryName'))->toContain('Housing')
                ->and(collect($props['income'])->pluck('categoryName'))->toContain('Salary')
                ->and(collect($props['income'])->pluck('categoryName'))->not->toContain('Housing');
        });
});

it('reports another user year as missing', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $this->actingAs($intruder)
        ->get(route('comparison.index', ['year' => 2027]))
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2027)->exists())->toBeTrue();
});

it('lives under the reports hub and redirects from where it used to be', function (): void {
    [$user] = userWithYear();

    expect(route('comparison.index', ['year' => 2027], absolute: false))
        ->toBe('/years/2027/reports/comparison');

    $this->actingAs($user)
        ->get('/years/2027/comparison')
        ->assertRedirect(route('comparison.index', ['year' => 2027]));
});
