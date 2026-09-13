<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Subscription;

/*
 * Another user's subscription is reported as missing rather than forbidden, so nothing
 * leaks — not even that it exists (USR-02, USR-03).
 */

function otherUserSubscription(): Subscription
{
    $owner = planningUser();

    return Subscription::factory()->for($owner)->create([
        'name' => 'Theirs',
        'category_id' => $owner->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->sole()->id,
    ]);
}

it('reports another user subscription as missing when changed', function (): void {
    $subscription = otherUserSubscription();
    $intruder = planningUser();

    $this->actingAs($intruder)
        ->patch(route('subscriptions.update', $subscription), [
            'name' => 'Mine now',
            'amount' => '1,00',
            'frequency' => 'monthly',
            'billing_anchor_date' => '2027-01-01',
            'category_id' => $intruder->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->sole()->id,
        ])
        ->assertNotFound();

    expect($subscription->refresh()->name)->toBe('Theirs');
});

it('reports another user subscription as missing when stopped', function (): void {
    $subscription = otherUserSubscription();

    $this->actingAs(planningUser())
        ->delete(route('subscription-activation.destroy', $subscription))
        ->assertNotFound();

    expect($subscription->refresh()->is_active)->toBeTrue();
});

it('reports another user subscription as missing when started again', function (): void {
    $subscription = otherUserSubscription();
    $subscription->update(['is_active' => false, 'deactivated_on' => '2027-04-30']);

    $this->actingAs(planningUser())
        ->post(route('subscription-activation.store', $subscription))
        ->assertNotFound();

    expect($subscription->refresh()->is_active)->toBeFalse();
});

it('shows each user only their own subscriptions', function (): void {
    otherUserSubscription();

    $intruder = planningUser();

    Subscription::factory()->for($intruder)->create([
        'name' => 'Mine',
        'category_id' => $intruder->categories()->where('system_key', Category::KEY_SUBSCRIPTIONS)->sole()->id,
    ]);

    $this->actingAs($intruder)
        ->get(route('subscriptions.index'))
        ->assertInertia(function ($page): void {
            $names = collect($page->toArray()['props']['subscriptions'])->pluck('name');

            expect($names->all())->toBe(['Mine']);
        });
});
