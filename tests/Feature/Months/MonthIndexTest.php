<?php

declare(strict_types=1);

use App\Enums\MonthStatus;
use App\Enums\TransactionType;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;

function monthIndexRecord(User $user, string $categoryName, string $date, int $cents): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
    ]);
}

it('shows a card for every month of the year', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->get(route('months.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->component('months/index')
            ->where('year', 2027)
            ->has('months', 12)
            ->where('months.0.month', 1)
            ->where('months.11.month', 12));
});

it('reports what each month actually came to', function (): void {
    [$user] = userWithYear();

    monthIndexRecord($user, 'Salary', '2027-03-25', 200_000);
    monthIndexRecord($user, 'Housing', '2027-03-05', 70_000);

    $this->actingAs($user)
        ->get(route('months.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.2.incomeCents', 200_000)
            ->where('months.2.expenseCents', 70_000)
            ->where('months.2.netCents', 130_000));
});

it('lets a month report a negative net', function (): void {
    [$user] = userWithYear();

    monthIndexRecord($user, 'Housing', '2027-01-05', 70_000);

    $this->actingAs($user)
        ->get(route('months.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page->where('months.0.netCents', -70_000));
});

it('says where each month stands', function (): void {
    [$user, $year] = userWithYear();

    monthIndexRecord($user, 'Housing', '2027-02-05', 1_000);
    MonthClosure::factory()->for($year)->create(['month' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('months.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.0.status', MonthStatus::Complete->value)
            ->where('months.1.status', MonthStatus::InProgress->value)
            ->where('months.2.status', MonthStatus::NotStarted->value));
});

it('counts the transactions in each month that need putting right', function (): void {
    [$user] = userWithYear();

    // Filed under a category recording the opposite direction, so flagged (TXV-03).
    Transaction::factory()->count(2)->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Salary')->firstOrFail()->id,
        'occurred_on' => '2027-04-05',
        'amount_cents' => 5_000,
    ]);

    monthIndexRecord($user, 'Housing', '2027-04-06', 1_000);

    $this->actingAs($user)
        ->get(route('months.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.3.issueCount', 2)
            ->where('months.0.issueCount', 0));
});

it('leaves flagged amounts out of the month totals', function (): void {
    [$user] = userWithYear();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Salary')->firstOrFail()->id,
        'occurred_on' => '2027-05-05',
        'amount_cents' => 99_999,
    ]);

    $this->actingAs($user)
        ->get(route('months.index', ['year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->where('months.4.expenseCents', 0)
            ->where('months.4.issueCount', 1)
            // Recorded, so the month has been started even though nothing counts yet.
            ->where('months.4.status', MonthStatus::InProgress->value));
});

it('reports another user year as missing', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $this->actingAs($intruder)
        ->get(route('months.index', ['year' => 2027]))
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2027)->exists())->toBeTrue();
});
