<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Enums\TransactionType;
use App\Enums\VarianceStatus;
use App\Models\FinancialYear;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

function reviewPlan(FinancialYear $year, User $user, string $categoryName, int $cents): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function reviewRecord(User $user, string $categoryName, string $date, int $cents, ?string $subcategoryName = null): void
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

it('shows what the month came to against the plan', function (): void {
    [$user, $year] = userWithYear();

    reviewPlan($year, $user, 'Housing', 70_000);
    reviewRecord($user, 'Housing', '2027-03-05', 80_000);
    reviewRecord($user, 'Salary', '2027-03-25', 200_000);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(fn ($page) => $page
            ->component('months/show')
            ->where('month', 3)
            ->where('totals.incomeCents', 200_000)
            ->where('totals.expenseCents', 80_000)
            ->where('totals.netCents', 120_000)
            ->where('totals.plannedExpenseCents', 70_000));
});

it('lists what still needs putting right, with reasons', function (): void {
    [$user] = userWithYear();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Salary')->firstOrFail()->id,
        'occurred_on' => '2027-03-05',
        'amount_cents' => 5_000,
        'description' => 'Misfiled',
    ]);

    reviewRecord($user, 'Housing', '2027-03-06', 1_000);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.description', 'Misfiled')
            ->where('issues.0.reasons.0', 'Its category records money moving the other way.'));
});

it('says nothing is wrong when nothing is', function (): void {
    [$user] = userWithYear();

    reviewRecord($user, 'Housing', '2027-03-05', 1_000);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(fn ($page) => $page->has('issues', 0));
});

it('ranks the categories that drifted furthest first', function (): void {
    [$user, $year] = userWithYear();

    reviewPlan($year, $user, 'Housing', 70_000);
    reviewPlan($year, $user, 'Utilities', 20_000);

    reviewRecord($user, 'Housing', '2027-03-05', 71_000);
    reviewRecord($user, 'Utilities', '2027-03-05', 60_000);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(fn ($page) => $page
            ->where('expenses.0.categoryName', 'Utilities')
            ->where('expenses.0.status', VarianceStatus::Over->value)
            ->where('expenses.0.label', 'Over budget')
            ->where('expenses.1.categoryName', 'Housing')
            ->where('expenses.1.status', VarianceStatus::Warning->value));
});

it('breaks a category down by subcategory', function (): void {
    [$user] = userWithYear();

    reviewRecord($user, 'Food & Groceries', '2027-03-05', 3_000, 'Supermarket');
    reviewRecord($user, 'Food & Groceries', '2027-03-06', 2_000);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page): void {
            $food = collect($page->toArray()['props']['expenses'])
                ->firstWhere('categoryName', 'Food & Groceries');

            $names = collect($food['subcategories'])->pluck('name');

            // Money filed straight against the category still has to appear, or the
            // breakdown would not add up to the total above it (CAT-08).
            expect($names)->toContain('Supermarket')
                ->and($names)->toContain('No subcategory');
        });
});

it('reports the balance the month opened and closed with', function (): void {
    [$user, $year] = userWithYear();

    $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $cash->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 100_000]);

    reviewRecord($user, 'Housing', '2027-01-05', 30_000);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('totals.openingCents', 100_000)
            ->where('totals.closingCents', 70_000)
            ->where('totals.hasActual', true));
});

it('falls back to the forecast balance for a month not yet reached', function (): void {
    [$user] = userWithYear();

    // 2027 is ahead of the frozen clock, so no month has been reached yet.
    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 6]))
        ->assertInertia(fn ($page) => $page->where('totals.hasActual', false));
});

it('refuses a month that does not exist', function (string $month): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => $month]))
        ->assertNotFound();
})->with(['0', '13', '99']);

it('reports another user month as missing', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $this->actingAs($intruder)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2027)->exists())->toBeTrue();
});
