<?php

declare(strict_types=1);

use App\Actions\CompleteMonth;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

function closureFor(FinancialYear $year, int $month): ?MonthClosure
{
    return MonthClosure::query()
        ->where('financial_year_id', $year->id)
        ->where('month', $month)
        ->first();
}

function completionRecord(User $user, string $categoryName, string $date): void
{
    $category = $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
        'amount_cents' => 1_000,
    ]);
}

function flagMonth(User $user, string $date): void
{
    // Filed under a category that records the opposite direction (TXV-03).
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Salary')->firstOrFail()->id,
        'occurred_on' => $date,
        'amount_cents' => 5_000,
    ]);
}

it('finishes a month that is behind us', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    completionRecord($user, 'Housing', '2026-03-05');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 3]))
        ->assertSessionHasNoErrors();

    expect(closureFor($year, 3)?->completed_at)->not->toBeNull();
});

it('refuses to finish a month with transactions still to fix', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    flagMonth($user, '2026-03-05');
    flagMonth($user, '2026-03-06');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 3]))
        ->assertSessionHasErrors(['month' => 'Fix 2 transactions first.']);

    expect(closureFor($year, 3))->toBeNull();
});

it('counts a single transaction to fix in the singular', function (): void {
    [$user] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    flagMonth($user, '2026-03-05');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 3]))
        ->assertSessionHasErrors(['month' => 'Fix 1 transaction first.']);
});

it('refuses to finish a month that has not started', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 8]))
        ->assertSessionHasErrors(['month' => 'This month has not started yet.']);

    expect(closureFor($year, 8))->toBeNull();
});

it('asks before finishing the month we are living through', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 5]))
        ->assertSessionHasErrors('confirmed');

    expect(closureFor($year, 5))->toBeNull();
});

it('finishes the current month once the user says they mean it', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 5]), ['confirmed' => true])
        ->assertSessionHasNoErrors();

    expect(closureFor($year, 5)?->completed_at)->not->toBeNull();
});

it('asks nothing on the last day of the month', function (): void {
    [$user, $year] = userWithYear(2026);

    // The month is over; there is nothing left to warn about.
    $this->travelTo('2026-05-31');

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 5]))
        ->assertSessionHasNoErrors();

    expect(closureFor($year, 5)?->completed_at)->not->toBeNull();
});

it('reads the last day of the month in the user own timezone', function (): void {
    [$user, $year] = userWithYear(2026);

    $user->preference->update(['timezone' => 'Pacific/Kiritimati']);

    // 31 May 22:00 UTC is already 1 June where the user lives, so May is behind them.
    $this->travelTo('2026-05-31 22:00:00');

    $this->actingAs($user->fresh() ?? $user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 5]))
        ->assertSessionHasNoErrors();

    expect(closureFor($year, 5)?->completed_at)->not->toBeNull();
});

it('reopens a finished month', function (): void {
    [$user, $year] = userWithYear(2026);

    MonthClosure::factory()->for($year)->create(['month' => 3, 'completed_at' => now()]);

    $this->actingAs($user)
        ->delete(route('month-completion.destroy', ['year' => 2026, 'month' => 3]))
        ->assertSessionHasNoErrors();

    // The row stays: it records that the month was once finished, and status is derived
    // from completed_at alone (MON-01).
    expect(closureFor($year, 3))->not->toBeNull()
        ->and(closureFor($year, 3)?->completed_at)->toBeNull();
});

it('finishes a month again after it was reopened', function (): void {
    [$user, $year] = userWithYear(2026);

    $this->travelTo('2026-05-10');

    MonthClosure::factory()->for($year)->create(['month' => 3, 'completed_at' => null]);

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 3]))
        ->assertSessionHasNoErrors();

    expect(MonthClosure::query()->where('financial_year_id', $year->id)->where('month', 3)->count())->toBe(1)
        ->and(closureFor($year, 3)?->completed_at)->not->toBeNull();
});

it('refuses a month number that does not exist', function (string $month): void {
    [$user] = userWithYear(2026);

    $this->actingAs($user)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => $month]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('month-completion.destroy', ['year' => 2026, 'month' => $month]))
        ->assertNotFound();
})->with(['0', '13']);

it('reports another user year as missing', function (): void {
    [$owner] = userWithYear(2026);
    [$intruder] = userWithYear(2025);

    $this->actingAs($intruder)
        ->post(route('month-completion.store', ['year' => 2026, 'month' => 3]))
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2026)->exists())->toBeTrue();
});

it('says whether finishing a month needs confirming', function (): void {
    [, $year] = userWithYear(2026);

    $action = resolve(CompleteMonth::class);
    $midMay = CarbonImmutable::parse('2026-05-10');

    expect($action->needsConfirmation($year, 5, $midMay))->toBeTrue()
        // A month already behind us needs no warning.
        ->and($action->needsConfirmation($year, 4, $midMay))->toBeFalse()
        // Nor does one in a year we are not in.
        ->and($action->needsConfirmation($year, 5, CarbonImmutable::parse('2027-05-10')))->toBeFalse()
        ->and($action->needsConfirmation($year, 5, CarbonImmutable::parse('2026-05-31')))->toBeFalse();
});
