<?php

declare(strict_types=1);

use App\Enums\Frequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;

/*
 * Recording what is charged on a schedule, what it costs a month, and stopping it
 * (SUB-01, SUB-02, SUB-04, CAT-07).
 */

function subsUser(): User
{
    return planningUser();
}

function subsCategory(User $user): Category
{
    $category = $user->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->first();

    expect($category)->toBeInstanceOf(Category::class);

    return $category;
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function subsPayload(User $user, array $overrides = []): array
{
    return [
        'name' => 'Netflix',
        'amount' => '12,99',
        'frequency' => Frequency::Monthly->value,
        'billing_anchor_date' => '2027-03-15',
        'category_id' => (string) subsCategory($user)->id,
        ...$overrides,
    ];
}

it('lists nothing before anything is added', function (): void {
    $user = subsUser();

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(function ($page): void {
            $props = $page->toArray()['props'];

            expect($props['subscriptions'])->toBe([])
                ->and($props['monthlyTotalCents'])->toBe(0)
                ->and($props['annualTotalCents'])->toBe(0);
        });
});

it('offers only the user own top level active expense categories', function (): void {
    $user = subsUser();

    $user->categories()->create([
        'type' => TransactionType::Expense,
        'name' => 'Retired',
        'is_active' => false,
        'sort_order' => 99,
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(function ($page) use ($user): void {
            $categories = collect($page->toArray()['props']['categories']);

            expect($categories->pluck('name'))->not->toContain('Retired')
                ->and($categories->where('isDefault', true)->pluck('id')->all())
                ->toBe([subsCategory($user)->id]);
        });
});

it('records a subscription and gives it a subcategory of its own name', function (): void {
    $user = subsUser();

    $this->actingAs($user)
        ->from(route('subscriptions.index'))
        ->post(route('subscriptions.store'), subsPayload($user))
        ->assertRedirect(route('subscriptions.index'))
        ->assertSessionHas('status');

    $subscription = $user->subscriptions()->sole();

    expect($subscription->name)->toBe('Netflix')
        ->and($subscription->amount_cents->cents)->toBe(1_299)
        ->and($subscription->is_active)->toBeTrue()
        ->and($subscription->subcategory?->name)->toBe('Netflix')
        ->and($subscription->subcategory?->parent_id)->toBe(subsCategory($user)->id)
        ->and($subscription->subcategory?->type)->toBe(TransactionType::Expense);
});

it('reuses the subcategory rather than making a second one of the same name', function (): void {
    $user = subsUser();

    $existing = $user->categories()->create([
        'parent_id' => subsCategory($user)->id,
        'type' => TransactionType::Expense,
        'name' => 'Netflix',
        'is_active' => false,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user))
        ->assertSessionHasNoErrors();

    expect($user->subscriptions()->sole()->subcategory_id)->toBe($existing->id)
        ->and($existing->refresh()->is_active)->toBeTrue()
        ->and($user->categories()->where('name', 'Netflix')->count())->toBe(1);
});

it('accepts an account and notes', function (): void {
    $user = subsUser();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, [
            'account_id' => (string) $account->id,
            'notes' => 'Family plan',
        ]))
        ->assertSessionHasNoErrors();

    $subscription = $user->subscriptions()->sole();

    expect($subscription->account_id)->toBe($account->id)
        ->and($subscription->notes)->toBe('Family plan');
});

it('rejects an amount it cannot read', function (): void {
    $user = subsUser();

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, ['amount' => 'twelve']))
        ->assertSessionHasErrors('amount');

    expect($user->subscriptions()->count())->toBe(0);
});

it('rejects an amount of nothing', function (): void {
    $user = subsUser();

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, ['amount' => '0']))
        ->assertSessionHasErrors('amount');

    expect($user->subscriptions()->count())->toBe(0);
});

it('rejects a changed amount it cannot read, and one of nothing', function (): void {
    $user = subsUser();

    $this->actingAs($user)->post(route('subscriptions.store'), subsPayload($user));

    $subscription = $user->subscriptions()->sole();

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $subscription), subsPayload($user, ['amount' => 'twelve']))
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $subscription), subsPayload($user, ['amount' => '0,00']))
        ->assertSessionHasErrors('amount');

    expect($subscription->refresh()->amount_cents->cents)->toBe(1_299);
});

it('rejects a frequency with no whole month interval', function (): void {
    $user = subsUser();

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, ['frequency' => Frequency::Once->value]))
        ->assertSessionHasErrors('frequency');
});

it('rejects a subcategory as the category', function (): void {
    $user = subsUser();

    $child = $user->categories()->create([
        'parent_id' => subsCategory($user)->id,
        'type' => TransactionType::Expense,
        'name' => 'Streaming',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, ['category_id' => (string) $child->id]))
        ->assertSessionHasErrors('category_id');
});

it('rejects an income category', function (): void {
    $user = subsUser();

    $income = $user->categories()
        ->where('type', TransactionType::Income)
        ->whereNull('parent_id')
        ->firstOrFail();

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, ['category_id' => (string) $income->id]))
        ->assertSessionHasErrors('category_id');
});

it('rejects another user account', function (): void {
    $user = subsUser();
    $theirs = Account::factory()->for(subsUser())->create();

    $this->actingAs($user)
        ->post(route('subscriptions.store'), subsPayload($user, ['account_id' => (string) $theirs->id]))
        ->assertSessionHasErrors('account_id');
});

it('shows what each subscription costs a month and a year', function (): void {
    $user = subsUser();

    Subscription::factory()->for($user)->annual()->ofCents(120_00)->anchoredOn('2027-02-10')->create([
        'name' => 'Insurance',
        'category_id' => subsCategory($user)->id,
    ]);

    Subscription::factory()->for($user)->monthly()->ofCents(9_99)->anchoredOn('2027-01-05')->create([
        'name' => 'Music',
        'category_id' => subsCategory($user)->id,
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(function ($page): void {
            $props = $page->toArray()['props'];
            $rows = collect($props['subscriptions'])->keyBy('name');

            expect($rows['Insurance']['monthlyEquivalentCents'])->toBe(1_000)
                ->and($rows['Insurance']['annualCents'])->toBe(12_000)
                ->and($rows['Music']['monthlyEquivalentCents'])->toBe(999)
                ->and($rows['Music']['annualCents'])->toBe(11_988)
                ->and($props['monthlyTotalCents'])->toBe(1_999)
                ->and($props['annualTotalCents'])->toBe(23_988);
        });
});

it('derives the next billing date from the anchor rather than storing it', function (): void {
    $user = subsUser();
    $user->preferences()->update(['timezone' => 'Europe/Athens']);

    Subscription::factory()->for($user)->monthly()->anchoredOn('2020-01-31')->create([
        'name' => 'Gym',
        'category_id' => subsCategory($user)->id,
    ]);

    $this->travelTo('2027-02-05 09:00:00');

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(function ($page): void {
            $row = collect($page->toArray()['props']['subscriptions'])->firstOrFail();

            // February is short, so the charge clamps to its last day (EDGE-05).
            expect($row['nextBillingDate'])->toBe('2027-02-28');
        });
});

it('leaves stopped subscriptions out of the totals and shows no next charge', function (): void {
    $user = subsUser();

    Subscription::factory()->for($user)->monthly()->ofCents(5_00)->inactive('2027-04-30')->create([
        'name' => 'Cancelled',
        'category_id' => subsCategory($user)->id,
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(function ($page): void {
            $props = $page->toArray()['props'];
            $row = collect($props['subscriptions'])->firstOrFail();

            expect($props['monthlyTotalCents'])->toBe(0)
                ->and($props['annualTotalCents'])->toBe(0)
                ->and($row['nextBillingDate'])->toBeNull()
                ->and($row['deactivatedOn'])->toBe('2027-04-30')
                ->and($row['isActive'])->toBeFalse();
        });
});

it('changes a subscription and keeps the old subcategory for what was already filed', function (): void {
    $user = subsUser();

    $this->actingAs($user)->post(route('subscriptions.store'), subsPayload($user));

    $subscription = $user->subscriptions()->sole();
    $originalSubcategory = $subscription->subcategory_id;

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $subscription), subsPayload($user, [
            'name' => 'Netflix Premium',
            'amount' => '19,99',
        ]))
        ->assertSessionHasNoErrors();

    $subscription->refresh();

    expect($subscription->name)->toBe('Netflix Premium')
        ->and($subscription->amount_cents->cents)->toBe(1_999)
        ->and($subscription->subcategory?->name)->toBe('Netflix Premium')
        ->and($subscription->subcategory_id)->not->toBe($originalSubcategory)
        ->and($user->categories()->where('name', 'Netflix')->exists())->toBeTrue();
});

it('stops a subscription on the day it was cancelled', function (): void {
    $user = subsUser();
    $user->preferences()->update(['timezone' => 'Europe/Athens']);

    $subscription = Subscription::factory()->for($user)->monthly()->create([
        'category_id' => subsCategory($user)->id,
    ]);

    $this->travelTo('2027-05-20 22:30:00');

    $this->actingAs($user)
        ->delete(route('subscription-activation.destroy', $subscription))
        ->assertSessionHas('status');

    $subscription->refresh();

    expect($subscription->is_active)->toBeFalse()
        ->and($subscription->deactivated_on?->toDateString())->toBe('2027-05-21');
});

it('starts a stopped subscription again and brings its subcategory back', function (): void {
    $user = subsUser();

    $this->actingAs($user)->post(route('subscriptions.store'), subsPayload($user));

    $subscription = $user->subscriptions()->sole();
    $subcategory = $subscription->subcategory;

    $this->actingAs($user)->delete(route('subscription-activation.destroy', $subscription));

    $subcategory?->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('subscription-activation.store', $subscription))
        ->assertSessionHas('status');

    $subscription->refresh();

    expect($subscription->is_active)->toBeTrue()
        ->and($subscription->deactivated_on)->toBeNull()
        ->and($subscription->subcategory_id)->toBe($subcategory?->id)
        ->and($subcategory?->refresh()->is_active)->toBeTrue();
});

it('refuses to retire a category a subscription is still charged against', function (): void {
    $user = subsUser();

    $this->actingAs($user)->post(route('subscriptions.store'), subsPayload($user));

    $subcategory = $user->subscriptions()->sole()->subcategory;

    $this->actingAs($user)
        ->delete(route('categories.destroy', $subcategory))
        ->assertForbidden();

    expect($subcategory?->refresh()->is_active)->toBeTrue();
});

it('allows retiring the category once the subscription is stopped', function (): void {
    $user = subsUser();

    $this->actingAs($user)->post(route('subscriptions.store'), subsPayload($user));

    $subscription = $user->subscriptions()->sole();
    $subcategory = $subscription->subcategory;

    $this->actingAs($user)->delete(route('subscription-activation.destroy', $subscription));

    $this->actingAs($user)
        ->delete(route('categories.destroy', $subcategory))
        ->assertRedirectToRoute('categories.index');

    expect($subcategory?->refresh()->is_active)->toBeFalse();
});

it('counts a subscription as a use of its category', function (): void {
    $user = subsUser();

    $spare = $user->categories()->create([
        'type' => TransactionType::Expense,
        'name' => 'Spare',
        'is_active' => true,
        'sort_order' => 50,
    ]);

    expect($spare->isInUse())->toBeFalse();

    Subscription::factory()->for($user)->create(['category_id' => $spare->id]);

    expect($spare->refresh()->isInUse())->toBeTrue();
});

it('renders as a tab of the plan', function (): void {
    [$user] = userWithYear();

    // The address does not change — a subscription is not year-scoped — but the page it
    // renders now sits inside the plan, where its effect on the budget is visible.
    expect(route('subscriptions.index', absolute: false))->toBe('/subscriptions');

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(fn ($page) => $page->component('plan/subscriptions'));
});
