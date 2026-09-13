<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\SyncSubscriptionPlanItems;
use App\Enums\Allocation;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\PlanItem;
use App\Models\Subscription;
use App\Models\User;

/*
 * A subscription plans itself: exactly one generated item per year, following the billing
 * dates, rebuilt whenever the subscription changes and never for a year already past
 * (SUB-04, SUB-05, YEAR-03).
 */

function syncSubsCategory(User $user): Category
{
    return $user->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->sole();
}

function syncSubscription(User $user, array $attributes = []): Subscription
{
    return Subscription::factory()->for($user)->create([
        'category_id' => syncSubsCategory($user)->id,
        ...$attributes,
    ]);
}

/**
 * @return array<int, int>
 */
function syncMonthsOf(PlanItem $item): array
{
    return $item->amounts()->orderBy('month')->get()
        ->mapWithKeys(fn ($amount): array => [$amount->month => $amount->amount_cents->cents])
        ->all();
}

function syncGeneratedItems(FinancialYear $year)
{
    return $year->planItems()->where('source', PlanItemSource::Subscription)->orderBy('sort_order')->get();
}

it('generates one item per subscription, charged in its billing months', function (): void {
    $user = planningUser();

    syncSubscription($user, [
        'name' => 'Insurance',
        'amount_cents' => 240_00,
        'frequency' => 'quarterly',
        'billing_anchor_date' => '2027-02-10',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $items = syncGeneratedItems($year);

    expect($items)->toHaveCount(1);

    $item = $items->firstOrFail();

    expect($item->name)->toBe('Insurance')
        ->and($item->type)->toBe(TransactionType::Expense)
        ->and($item->kind)->toBe(PlanItemKind::Recurring)
        ->and($item->allocation)->toBe(Allocation::LumpSum)
        ->and($item->category_id)->toBe(syncSubsCategory($user)->id)
        ->and($item->start_month)->toBe(2)
        ->and(syncMonthsOf($item))->toBe([
            1 => 0, 2 => 240_00, 3 => 0, 4 => 0,
            5 => 240_00, 6 => 0, 7 => 0, 8 => 240_00,
            9 => 0, 10 => 0, 11 => 240_00, 12 => 0,
        ]);
});

it('plans the months before the anchor too, since the anchor is the next charge', function (): void {
    $user = planningUser();

    syncSubscription($user, [
        'amount_cents' => 10_00,
        'frequency' => 'monthly',
        'billing_anchor_date' => '2029-05-01',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    // The only date stored is one charge, and for a long-running subscription that is
    // usually the next one — so the sequence runs backwards from it as well (SUB-03).
    expect(array_sum(syncMonthsOf(syncGeneratedItems($year)->firstOrFail())))->toBe(120_00);
});

it('leaves no item for a year after the subscription was stopped', function (): void {
    $user = planningUser();

    syncSubscription($user, [
        'frequency' => 'monthly',
        'billing_anchor_date' => '2026-01-10',
        'is_active' => false,
        'deactivated_on' => '2026-08-31',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    expect(syncGeneratedItems($year))->toHaveCount(0);
});

it('zeroes the months after a subscription was stopped', function (): void {
    $user = planningUser();

    $subscription = syncSubscription($user, [
        'name' => 'Netflix',
        'amount_cents' => 12_99,
        'frequency' => 'monthly',
        'billing_anchor_date' => '2027-01-20',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $this->travelTo('2027-04-10 10:00:00');

    $this->actingAs($user)->delete(route('subscription-activation.destroy', $subscription));

    $months = syncMonthsOf(syncGeneratedItems($year)->firstOrFail());

    expect(array_slice($months, 0, 4, true))->toBe([1 => 12_99, 2 => 12_99, 3 => 12_99, 4 => 0])
        ->and(array_sum(array_slice($months, 4, 8, true)))->toBe(0);
});

it('brings the months back when a subscription is started again', function (): void {
    $user = planningUser();

    $subscription = syncSubscription($user, [
        'amount_cents' => 10_00,
        'frequency' => 'monthly',
        'billing_anchor_date' => '2027-01-05',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $this->travelTo('2027-03-01 09:00:00');

    $this->actingAs($user)->delete(route('subscription-activation.destroy', $subscription));

    expect(array_sum(syncMonthsOf(syncGeneratedItems($year)->firstOrFail())))->toBe(20_00);

    $this->actingAs($user)->post(route('subscription-activation.store', $subscription));

    expect(array_sum(syncMonthsOf(syncGeneratedItems($year)->firstOrFail())))->toBe(120_00);
});

it('re-syncs the current and future years but not a past one', function (): void {
    $user = planningUser();

    $subscription = syncSubscription($user, [
        'amount_cents' => 10_00,
        'frequency' => 'monthly',
        'billing_anchor_date' => '2026-01-10',
    ]);

    $past = resolve(CreateFinancialYear::class)->handle($user, 2026);
    $current = resolve(CreateFinancialYear::class)->handle($user, 2027);
    $future = resolve(CreateFinancialYear::class)->handle($user, 2028);

    $this->travelTo('2027-06-15 09:00:00');

    resolve(SyncSubscriptionPlanItems::class);

    $this->actingAs($user)->patch(route('subscriptions.update', $subscription), [
        'name' => $subscription->name,
        'amount' => '25,00',
        'frequency' => 'monthly',
        'billing_anchor_date' => '2026-01-10',
        'category_id' => syncSubsCategory($user)->id,
    ])->assertSessionHasNoErrors();

    expect(array_sum(syncMonthsOf(syncGeneratedItems($past)->firstOrFail())))->toBe(120_00)
        ->and(array_sum(syncMonthsOf(syncGeneratedItems($current)->firstOrFail())))->toBe(300_00)
        ->and(array_sum(syncMonthsOf(syncGeneratedItems($future)->firstOrFail())))->toBe(300_00);
});

it('keeps exactly one item however often it syncs', function (): void {
    $user = planningUser();
    $subscription = syncSubscription($user, ['billing_anchor_date' => '2027-01-10']);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    $sync = resolve(SyncSubscriptionPlanItems::class);

    $sync->handle($year);
    $sync->handle($year);
    $sync->handle($year);

    expect(syncGeneratedItems($year))->toHaveCount(1)
        ->and($year->planItems()->where('subscription_id', $subscription->id)->count())->toBe(1);
});

it('removes an item once its subscription no longer charges in the year', function (): void {
    $user = planningUser();
    $subscription = syncSubscription($user, [
        'frequency' => 'monthly',
        'billing_anchor_date' => '2027-01-10',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2027);

    expect(syncGeneratedItems($year))->toHaveCount(1);

    $subscription->update(['is_active' => false, 'deactivated_on' => '2026-12-31']);

    resolve(SyncSubscriptionPlanItems::class)->handle($year);

    expect(syncGeneratedItems($year))->toHaveCount(0)
        ->and($year->planItems()->count())->toBe(0);
});

it('leaves manual plan items alone', function (): void {
    [$user, $year] = userWithYear(2027);

    $manual = PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => syncSubsCategory($user)->id,
        'name' => 'Something by hand',
        'source' => PlanItemSource::Manual,
    ]);

    syncSubscription($user, ['billing_anchor_date' => '2027-01-10']);

    resolve(SyncSubscriptionPlanItems::class)->handle($year);

    expect($year->planItems()->whereKey($manual->id)->exists())->toBeTrue()
        ->and(syncGeneratedItems($year))->toHaveCount(1);
});

it('creates the item for a year opened after the subscription', function (): void {
    $user = planningUser();

    syncSubscription($user, [
        'amount_cents' => 30_00,
        'frequency' => 'annual',
        'billing_anchor_date' => '2027-07-04',
    ]);

    $year = resolve(CreateFinancialYear::class)->handle($user, 2028);

    $item = syncGeneratedItems($year)->firstOrFail();

    expect($item->start_month)->toBe(7)
        ->and(array_sum(syncMonthsOf($item)))->toBe(30_00);
});

it('generates the item when a subscription is added after the year exists', function (): void {
    [$user, $year] = userWithYear(2027);

    $this->travelTo('2027-02-01 09:00:00');

    expect(syncGeneratedItems($year))->toHaveCount(0);

    $this->actingAs($user)
        ->post(route('subscriptions.store'), [
            'name' => 'Spotify',
            'amount' => '9,99',
            'frequency' => 'monthly',
            'billing_anchor_date' => '2027-01-15',
            'category_id' => syncSubsCategory($user)->id,
        ])
        ->assertSessionHasNoErrors();

    $item = syncGeneratedItems($year)->firstOrFail();

    expect($item->name)->toBe('Spotify')
        ->and($item->subcategory_id)->toBe($user->subscriptions()->sole()->subcategory_id)
        ->and(array_sum(syncMonthsOf($item)))->toBe(119_88);
});

it('warns on the plan screen when a manual item names an active subscription', function (): void {
    [$user, $year] = userWithYear(2027);

    syncSubscription($user, ['name' => 'Netflix', 'billing_anchor_date' => '2027-01-10']);

    PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => syncSubsCategory($user)->id,
        'type' => TransactionType::Expense,
        'kind' => PlanItemKind::Recurring,
        'name' => 'netflix',
        'source' => PlanItemSource::Manual,
    ]);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => $year->year, 'tab' => 'expenses']))
        ->assertInertia(function ($page) use ($user): void {
            $rows = collect($page->toArray()['props']['rows'])->keyBy('categoryId');

            expect($rows[syncSubsCategory($user)->id]['doubleCounts'])->toBe(['Netflix']);
        });
});

it('does not warn for a generated item or a stopped subscription', function (): void {
    [$user, $year] = userWithYear(2027);

    $subscription = syncSubscription($user, ['name' => 'Netflix', 'billing_anchor_date' => '2027-01-10']);

    resolve(SyncSubscriptionPlanItems::class)->handle($year);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => $year->year, 'tab' => 'expenses']))
        ->assertInertia(function ($page) use ($user): void {
            $rows = collect($page->toArray()['props']['rows'])->keyBy('categoryId');

            // The generated item is the subscription, not a second copy of it.
            expect($rows[syncSubsCategory($user)->id]['doubleCounts'])->toBe([]);
        });

    PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => syncSubsCategory($user)->id,
        'type' => TransactionType::Expense,
        'kind' => PlanItemKind::Recurring,
        'name' => 'Netflix',
        'source' => PlanItemSource::Manual,
    ]);

    $subscription->update(['is_active' => false, 'deactivated_on' => '2027-01-31']);

    $this->actingAs($user)
        ->get(route('plan.show', ['year' => $year->year, 'tab' => 'expenses']))
        ->assertInertia(function ($page) use ($user): void {
            $rows = collect($page->toArray()['props']['rows'])->keyBy('categoryId');

            expect($rows[syncSubsCategory($user)->id]['doubleCounts'])->toBe([]);
        });
});
