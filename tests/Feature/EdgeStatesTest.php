<?php

declare(strict_types=1);

use App\Actions\CalculateMonthlyFigures;
use App\Actions\CompleteYearSetup;
use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

/*
 * The states a screen falls into when there is nothing to show, nothing to divide by, or
 * a date that does not exist (EDGE-01 … EDGE-05).
 */

function edgeItem(FinancialYear $year, User $user, array $attributes = []): PlanItem
{
    return resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', 'Holidays')->whereNull('parent_id')->firstOrFail()->id,
        'name' => 'Summer holiday',
        'type' => TransactionType::Expense->value,
        'kind' => PlanItemKind::Irregular->value,
        'frequency' => Frequency::Once->value,
        'start_month' => 2,
        ...$attributes,
    ], Money::fromCents(120_000));
}

/*
 * EDGE-05: a payment day past the end of a month resolves to its last day.
 */

it('resolves a payment day of 31 to the last day of a short month', function (int $year, int $month, string $expected): void {
    $user = planningUser();
    $financialYear = resolve(CreateFinancialYear::class)->handle($user, $year);

    $item = edgeItem($financialYear, $user, ['payment_day' => 31]);

    expect($item->paymentDateIn($month)?->toDateString())->toBe($expected);
})->with([
    // 2027 is not a leap year, 2028 is. February is the month that tells them apart.
    'February, ordinary year' => [2027, 2, '2027-02-28'],
    'February, leap year' => [2028, 2, '2028-02-29'],
    'April, thirty days' => [2027, 4, '2027-04-30'],
    'March, thirty-one days' => [2027, 3, '2027-03-31'],
]);

it('leaves the day alone when the month is long enough', function (): void {
    [$user, $year] = userWithYear();

    $item = edgeItem($year, $user, ['payment_day' => 15]);

    expect($item->paymentDateIn(2)?->toDateString())->toBe('2027-02-15')
        ->and($item->paymentDateIn(12)?->toDateString())->toBe('2027-12-15');
});

it('has no due date at all when no payment day was given', function (): void {
    [$user, $year] = userWithYear();

    expect(edgeItem($year, $user)->paymentDateIn(2))->toBeNull();
});

it('sends the resolved due date to the plan screen', function (): void {
    [$user, $year] = userWithYear();

    edgeItem($year, $user, ['payment_day' => 31]);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => 2027, 'tab' => 'irregular']))
        ->assertInertia(function ($page): void {
            $item = collect($page->toArray()['props']['items'])->firstOrFail();

            expect($item['paymentDay'])->toBe(31)
                ->and($item['dueOn'])->toBe('2027-02-28');
        });
});

/*
 * EDGE-03: nothing divides by a plan of zero.
 */

it('reports a category with no plan and no spending without dividing by it', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->get(route('comparison.index', ['year' => 2027]))
        ->assertOk()
        ->assertInertia(function ($page): void {
            // Every category is planned at zero, which is the case a percentage cannot be
            // taken of. The screen sends cents and lets the interface say "—".
            expect($page->toArray()['props']['expenses'])->not->toBeNull();
        });
});

it('reports a year with no income at all rather than failing on the savings rate', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function ($page): void {
            $props = $page->toArray()['props'];

            expect($props['savings']['actualIncomeCents'])->toBe(0)
                ->and($props['savings']['actualCents'])->toBe(0);
        });
});

/*
 * EDGE-04: a transaction dated ahead of today still counts for its month.
 */

it('counts a future transaction as actual for the month it falls in', function (): void {
    [$user, $year] = userWithYear();

    $this->travelTo('2027-03-15 09:00:00');

    $category = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $category->id,
        'occurred_on' => '2027-03-28',
        'amount_cents' => 70_000,
    ]);

    $figures = resolve(CalculateMonthlyFigures::class)->handle($year);

    expect($figures->month(3)->actualExpenseCents)->toBe(70_000);
});

it('accepts a transaction dated in the future', function (): void {
    [$user] = userWithYear();

    $this->travelTo('2027-03-15 09:00:00');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => '25,00',
            'occurred_on' => '2027-03-28',
            'category_id' => $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->id,
            'description' => 'Booked ahead',
        ])
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->occurred_on->toDateString())->toBe('2027-03-28');
});

/*
 * EDGE-02: a year whose setup was never finished says so.
 */

it('marks a half-finished year as such in the shared props', function (): void {
    [$user, $year] = userWithYear();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(function ($page): void {
            $years = collect($page->toArray()['props']['years']);

            expect($years->firstOrFail()['isSetupComplete'])->toBeFalse();
        });

    resolve(CompleteYearSetup::class)->handle($year);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(function ($page): void {
            $years = collect($page->toArray()['props']['years']);

            expect($years->firstOrFail()['isSetupComplete'])->toBeTrue();
        });
});

/*
 * EDGE-01: every list says what to do next when it has nothing in it.
 */

it('renders every list screen with nothing recorded', function (string $route): void {
    [$user] = userWithYear();

    $this->actingAs($user)->get($route)->assertOk();
})->with(fn (): array => [
    'transactions' => ['/transactions'],
    'goals' => ['/goals'],
    'subscriptions' => ['/subscriptions'],
    'net worth' => ['/goals/net-worth'],
    'months' => ['/years/2027/months'],
    'comparison' => ['/years/2027/reports/comparison'],
    'cash flow' => ['/years/2027/reports/cash-flow'],
    'forecast' => ['/years/2027/reports/forecast'],
    'dashboard' => ['/dashboard'],
]);
