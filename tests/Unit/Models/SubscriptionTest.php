<?php

declare(strict_types=1);

use App\Enums\Frequency;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/*
 * SUB-03 and EDGE-05. Only one date is stored; every other billing date is derived, so
 * these check the derivation rather than any stored schedule.
 */

function subscriptionAnchoredOn(string $date, Frequency $frequency = Frequency::Monthly): Subscription
{
    return Subscription::factory()->anchoredOn($date)->make(['frequency' => $frequency]);
}

function nextBilling(Subscription $subscription, string $from): string
{
    return $subscription->nextBillingDateFrom(CarbonImmutable::parse($from))->toDateString();
}

it('charges again next month', function (): void {
    $subscription = subscriptionAnchoredOn('2027-01-15');

    expect(nextBilling($subscription, '2027-01-20'))->toBe('2027-02-15');
});

it('counts the day of the charge itself as next', function (): void {
    $subscription = subscriptionAnchoredOn('2027-01-15');

    // Asking on the day it is charged, the answer is today, not next month.
    expect(nextBilling($subscription, '2027-01-15'))->toBe('2027-01-15');
});

it('looks forward from an anchor in the past', function (): void {
    $subscription = subscriptionAnchoredOn('2020-03-08');

    expect(nextBilling($subscription, '2027-06-20'))->toBe('2027-07-08');
});

it('looks forward from an anchor in the future', function (): void {
    $subscription = subscriptionAnchoredOn('2028-05-10');

    // The anchor is any one charge, so the sequence runs backwards from it too.
    expect(nextBilling($subscription, '2027-06-20'))->toBe('2027-07-10');
});

it('steps a quarter at a time', function (): void {
    $subscription = subscriptionAnchoredOn('2027-01-10', Frequency::Quarterly);

    expect(nextBilling($subscription, '2027-01-11'))->toBe('2027-04-10')
        ->and(nextBilling($subscription, '2027-05-01'))->toBe('2027-07-10');
});

it('steps half a year at a time', function (): void {
    $subscription = subscriptionAnchoredOn('2027-02-20', Frequency::SemiAnnual);

    expect(nextBilling($subscription, '2027-03-01'))->toBe('2027-08-20');
});

it('steps a year at a time', function (): void {
    $subscription = subscriptionAnchoredOn('2027-09-01', Frequency::Annual);

    expect(nextBilling($subscription, '2027-10-01'))->toBe('2028-09-01');
});

it('charges on the last day of a month too short for the anchor day', function (): void {
    $subscription = subscriptionAnchoredOn('2027-01-31');

    // February has no 31st, so the charge lands on the 28th rather than rolling into
    // March (EDGE-05).
    expect(nextBilling($subscription, '2027-02-01'))->toBe('2027-02-28');
});

it('knows February is longer in a leap year', function (): void {
    $subscription = subscriptionAnchoredOn('2028-01-31');

    expect(nextBilling($subscription, '2028-02-01'))->toBe('2028-02-29');
});

it('returns to the anchor day once the month is long enough again', function (): void {
    $subscription = subscriptionAnchoredOn('2027-01-31');

    // The clamp applies to February alone; March is not dragged to the 28th with it.
    expect(nextBilling($subscription, '2027-03-01'))->toBe('2027-03-31')
        ->and(nextBilling($subscription, '2027-04-01'))->toBe('2027-04-30')
        ->and(nextBilling($subscription, '2027-05-01'))->toBe('2027-05-31');
});

it('is charged every month of the year when billed monthly', function (): void {
    $subscription = subscriptionAnchoredOn('2027-03-15');

    expect($subscription->billingMonthsIn(2027))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
});

it('is charged four times a year when billed quarterly', function (): void {
    $subscription = subscriptionAnchoredOn('2027-02-10', Frequency::Quarterly);

    expect($subscription->billingMonthsIn(2027))->toBe([2, 5, 8, 11]);
});

it('is charged twice a year when billed half-yearly', function (): void {
    $subscription = subscriptionAnchoredOn('2027-04-01', Frequency::SemiAnnual);

    expect($subscription->billingMonthsIn(2027))->toBe([4, 10]);
});

it('is charged once a year when billed annually', function (): void {
    $subscription = subscriptionAnchoredOn('2027-07-20', Frequency::Annual);

    expect($subscription->billingMonthsIn(2027))->toBe([7]);
});

it('keeps its month in later years', function (): void {
    $subscription = subscriptionAnchoredOn('2027-07-20', Frequency::Annual);

    expect($subscription->billingMonthsIn(2029))->toBe([7]);
});

it('is charged in a year before the anchor', function (): void {
    $subscription = subscriptionAnchoredOn('2028-03-10', Frequency::Quarterly);

    // The anchor names one charge; the sequence extends both ways from it.
    expect($subscription->billingMonthsIn(2027))->toBe([3, 6, 9, 12]);
});

it('stops being charged after it was stopped', function (): void {
    $subscription = Subscription::factory()
        ->anchoredOn('2027-01-10')
        ->inactive('2027-06-30')
        ->make();

    // The plan should show what was paid, not what would have been (SUB-04).
    expect($subscription->billingMonthsIn(2027))->toBe([1, 2, 3, 4, 5, 6]);
});

it('is charged in the month it was stopped if the charge came first', function (): void {
    $subscription = Subscription::factory()
        ->anchoredOn('2027-01-10')
        ->inactive('2027-06-15')
        ->make();

    // Charged on the 10th, stopped on the 15th: June was paid for.
    expect($subscription->billingMonthsIn(2027))->toBe([1, 2, 3, 4, 5, 6]);
});

it('is not charged in the month it was stopped if the charge came after', function (): void {
    $subscription = Subscription::factory()
        ->anchoredOn('2027-01-20')
        ->inactive('2027-06-15')
        ->make();

    // Stopped on the 15th, would have been charged on the 20th: June was not paid.
    expect($subscription->billingMonthsIn(2027))->toBe([1, 2, 3, 4, 5]);
});

it('is charged in no month of a year it had already stopped', function (): void {
    $subscription = Subscription::factory()
        ->anchoredOn('2027-01-10')
        ->inactive('2027-06-30')
        ->make();

    expect($subscription->billingMonthsIn(2028))->toBe([]);
});

it('says what it costs a month', function (): void {
    expect(Subscription::factory()->ofCents(1_299)->make()->monthlyEquivalentCents())->toBe(1_299)
        ->and(Subscription::factory()->quarterly()->ofCents(3_600)->make()->monthlyEquivalentCents())->toBe(1_200)
        ->and(Subscription::factory()->annual()->ofCents(12_000)->make()->monthlyEquivalentCents())->toBe(1_000);
});

it('rounds the monthly cost down rather than claiming precision', function (): void {
    // 100,00 a year is 8,33 a month, and the third of a cent is not real.
    expect(Subscription::factory()->annual()->ofCents(10_000)->make()->monthlyEquivalentCents())->toBe(833);
});

it('says what it costs a year', function (): void {
    expect(Subscription::factory()->ofCents(1_299)->make()->annualCents())->toBe(15_588)
        ->and(Subscription::factory()->quarterly()->ofCents(3_600)->make()->annualCents())->toBe(14_400)
        ->and(Subscription::factory()->annual()->ofCents(12_000)->make()->annualCents())->toBe(12_000);
});

it('treats an unexpected frequency as monthly', function (): void {
    // Frequency carries cases a subscription cannot use; a stored one would still have to
    // resolve to something rather than divide by nothing.
    $subscription = Subscription::factory()->make(['frequency' => Frequency::Custom]);

    expect($subscription->intervalMonths())->toBe(1);
});

it('looks forward from an anchor several intervals ahead', function (): void {
    $subscription = subscriptionAnchoredOn('2028-05-10', Frequency::Quarterly);

    // The anchor is a year and change ahead, and the gap is not a whole number of
    // quarters, so the walk back has to land on a real charge rather than between two.
    expect(nextBilling($subscription, '2027-01-01'))->toBe('2027-02-10')
        ->and(nextBilling($subscription, '2027-02-11'))->toBe('2027-05-10');
});

it('reaches a year several intervals after the anchor', function (): void {
    $subscription = subscriptionAnchoredOn('2020-11-05', Frequency::SemiAnnual);

    // Charges run May and November every year; 2027 is fourteen steps along.
    expect($subscription->billingMonthsIn(2027))->toBe([5, 11]);
});

it('belongs to a user, a category and an account', function (): void {
    $user = planningUser();

    $category = $user->categories()->where('name', 'Subscriptions')->firstOrFail();
    $account = $user->accounts()->firstOrFail();

    $subscription = Subscription::factory()->for($user)->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
    ]);

    expect($subscription->user->id)->toBe($user->id)
        ->and($subscription->category->name)->toBe('Subscriptions')
        ->and($subscription->account?->id)->toBe($account->id)
        ->and($subscription->subcategory)->toBeNull();
});
