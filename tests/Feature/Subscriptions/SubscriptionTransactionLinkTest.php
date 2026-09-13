<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;

/*
 * Choosing a subscription's subcategory is already saying which subscription a charge is
 * for, so the link is made from it rather than asked for again (SUB-06).
 */

function linkSubscription(User $user, string $name = 'Netflix'): Subscription
{
    $response = test()->actingAs($user)->post(route('subscriptions.store'), [
        'name' => $name,
        'amount' => '12,99',
        'frequency' => 'monthly',
        'billing_anchor_date' => '2027-01-15',
        'category_id' => $user->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->sole()->id,
    ]);

    $response->assertSessionHasNoErrors();

    return $user->subscriptions()->where('name', $name)->orderByDesc('id')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function linkCharge(Subscription $subscription, array $overrides = []): array
{
    return [
        'type' => TransactionType::Expense->value,
        'amount' => '12,99',
        'occurred_on' => '2027-03-15',
        'category_id' => $subscription->category_id,
        'subcategory_id' => $subscription->subcategory_id,
        'description' => $subscription->name,
        ...$overrides,
    ];
}

it('links a new transaction to the subscription owning its subcategory', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), linkCharge($subscription))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subscription_id)->toBe($subscription->id);
});

it('leaves a transaction unlinked when no subcategory was chosen', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), linkCharge($subscription, ['subcategory_id' => null]))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subscription_id)->toBeNull();
});

it('leaves a transaction unlinked when the subcategory is nobody subscription', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $other = $user->categories()->create([
        'parent_id' => $subscription->category_id,
        'type' => TransactionType::Expense,
        'name' => 'Something else',
        'is_active' => true,
        'sort_order' => 9,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), linkCharge($subscription, ['subcategory_id' => $other->id]))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subscription_id)->toBeNull();
});

it('links a stopped subscription charge, because a final invoice is still its own', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $this->actingAs($user)->delete(route('subscription-activation.destroy', $subscription));

    $this->actingAs($user)
        ->post(route('transactions.store'), linkCharge($subscription, ['occurred_on' => '2027-01-15']))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subscription_id)->toBe($subscription->id);
});

it('prefers the running subscription when two share a subcategory', function (): void {
    [$user] = userWithYear();

    $stopped = linkSubscription($user);

    $this->actingAs($user)->delete(route('subscription-activation.destroy', $stopped));

    // The same name again reuses the subcategory rather than making a second one (SUB-02).
    $restarted = linkSubscription($user, 'Netflix');

    expect($restarted->id)->not->toBe($stopped->id)
        ->and($restarted->subcategory_id)->toBe($stopped->subcategory_id);

    $this->actingAs($user)
        ->post(route('transactions.store'), linkCharge($restarted))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subscription_id)->toBe($restarted->id);
});

it('re-links a corrected transaction, and unlinks one moved away', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $this->actingAs($user)->post(route('transactions.store'), linkCharge($subscription));

    $transaction = $user->transactions()->sole();

    expect($transaction->subscription_id)->toBe($subscription->id);

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), linkCharge($subscription, [
            'subcategory_id' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->subscription_id)->toBeNull();

    $this->actingAs($user)
        ->patch(route('transactions.update', $transaction), linkCharge($subscription))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->subscription_id)->toBe($subscription->id);
});

it('never links to another user subscription sharing a subcategory id', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $intruder = planningUser();

    Subscription::factory()->for($intruder)->create([
        'name' => 'Theirs',
        'category_id' => $intruder->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->sole()->id,
        'subcategory_id' => $subscription->subcategory_id,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), linkCharge($subscription))
        ->assertSessionHasNoErrors();

    expect($user->transactions()->sole()->subscription_id)->toBe($subscription->id);
});

it('names the subscription on the transaction row', function (): void {
    [$user] = userWithYear();
    $subscription = linkSubscription($user);

    $this->actingAs($user)->post(route('transactions.store'), linkCharge($subscription));

    $this->actingAs($user)
        ->get(route('transactions.index', ['year' => 2027]))
        ->assertInertia(function ($page): void {
            $row = collect($page->toArray()['props']['transactions'])->firstOrFail();

            expect($row['subscriptionName'])->toBe('Netflix');
        });
});
