<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\User;

function nwHolding(User $user, NetWorthItemKind $kind): NetWorthItem
{
    return $user->netWorthItems()->where('kind', $kind)->firstOrFail();
}

function nwValue(FinancialYear $year, NetWorthItem $item, int $month, int $cents): void
{
    NetWorthSnapshot::query()->updateOrCreate(
        [
            'net_worth_item_id' => $item->id,
            'financial_year_id' => $year->id,
            'month' => $month,
        ],
        ['value_cents' => $cents],
    );
}

it('shows where the user stands', function (): void {
    [$user, $year] = userWithYear();

    nwValue($year, nwHolding($user, NetWorthItemKind::Cash), 3, 500_000);
    nwValue($year, nwHolding($user, NetWorthItemKind::Debt), 3, 200_000);

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(fn ($page) => $page
            ->component('goals/net-worth')
            ->where('hasYear', true)
            ->where('current.assetsCents', 500_000)
            ->where('current.debtsCents', 200_000)
            ->where('current.netCents', 300_000));
});

it('asks for a year before it can track anything', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(fn ($page) => $page
            ->where('hasYear', false)
            ->where('current', null));
});

it('reports the change since last month and since the year began', function (): void {
    [$user, $year] = userWithYear();

    $cash = nwHolding($user, NetWorthItemKind::Cash);

    nwValue($year, $cash, NetWorthSnapshot::OPENING_MONTH, 100_000);
    nwValue($year, $cash, 2, 150_000);
    nwValue($year, $cash, 3, 400_000);

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(fn ($page) => $page
            ->where('changeVsPreviousMonth', 250_000)
            ->where('changeVsStartOfYear', 300_000));
});

it('marks a value that is being carried forward', function (): void {
    [$user, $year] = userWithYear();

    $investment = nwHolding($user, NetWorthItemKind::Investment);
    $cash = nwHolding($user, NetWorthItemKind::Cash);

    nwValue($year, $investment, 1, 1_000_000);
    // A later month for something else, so the investment figure is stale by comparison.
    nwValue($year, $cash, 5, 100_000);

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(function ($page) use ($investment, $cash): void {
            $holdings = collect($page->toArray()['props']['current']['holdings']);

            expect($holdings->firstWhere('itemId', $investment->id)['isCarriedForward'])->toBeTrue()
                ->and($holdings->firstWhere('itemId', $cash->id)['isCarriedForward'])->toBeFalse();
        });
});

it('reports the opening position and all twelve months', function (): void {
    [, $year] = userWithYear();

    $this->actingAs($year->user)
        ->get(route('net-worth.index'))
        ->assertInertia(fn ($page) => $page
            ->has('months', 13)
            ->where('months.0.month', 0)
            ->where('months.12.month', 12));
});

it('adds something the user owns', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('net-worth-items.store'), [
            'name' => 'Flat',
            'kind' => NetWorthItemKind::OtherAsset->value,
        ])
        ->assertSessionHasNoErrors();

    $item = $user->netWorthItems()->where('name', 'Flat')->firstOrFail();

    expect($item->kind)->toBe(NetWorthItemKind::OtherAsset)
        ->and($item->is_active)->toBeTrue();
});

it('opens a new holding at nothing in every year the user has', function (): void {
    $user = planningUser();

    $twentySeven = resolve(CreateFinancialYear::class)->handle($user, 2027);
    $twentyEight = resolve(CreateFinancialYear::class)->handle($user, 2028);

    $this->actingAs($user)->post(route('net-worth-items.store'), [
        'name' => 'Flat',
        'kind' => NetWorthItemKind::OtherAsset->value,
    ]);

    $item = $user->netWorthItems()->where('name', 'Flat')->firstOrFail();

    // So it appears in net worth from the start of each year, not from the first month
    // the user happens to value it.
    foreach ([$twentySeven, $twentyEight] as $year) {
        expect(NetWorthSnapshot::query()
            ->where('net_worth_item_id', $item->id)
            ->where('financial_year_id', $year->id)
            ->where('month', NetWorthSnapshot::OPENING_MONTH)
            ->exists())->toBeTrue();
    }
});

it('needs a name and a kind', function (string $field): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('net-worth-items.store'), [
            'name' => 'Flat',
            'kind' => NetWorthItemKind::OtherAsset->value,
            $field => '',
        ])
        ->assertSessionHasErrors($field);
})->with(['name', 'kind']);

it('refuses a kind that is not one', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->post(route('net-worth-items.store'), ['name' => 'Flat', 'kind' => 'crypto'])
        ->assertSessionHasErrors('kind');
});

it('renames a holding and changes what kind it is', function (): void {
    [$user] = userWithYear();

    $item = nwHolding($user, NetWorthItemKind::Investment);

    $this->actingAs($user)
        ->patch(route('net-worth-items.update', $item), [
            'name' => 'Pension',
            'kind' => NetWorthItemKind::OtherAsset->value,
        ])
        ->assertSessionHasNoErrors();

    expect($item->refresh()->name)->toBe('Pension')
        ->and($item->kind)->toBe(NetWorthItemKind::OtherAsset);
});

it('retires a holding rather than removing it', function (): void {
    [$user, $year] = userWithYear();

    $item = nwHolding($user, NetWorthItemKind::Investment);

    nwValue($year, $item, 1, 900_000);

    $this->actingAs($user)
        ->delete(route('net-worth-items.destroy', $item))
        ->assertSessionHasNoErrors();

    // The holding and its history survive; it simply stops counting.
    expect($item->refresh()->is_active)->toBeFalse()
        ->and(NetWorthItem::query()->whereKey($item->id)->exists())->toBeTrue()
        ->and(NetWorthSnapshot::query()->where('net_worth_item_id', $item->id)->exists())->toBeTrue();
});

it('stops counting a retired holding', function (): void {
    [$user, $year] = userWithYear();

    $investment = nwHolding($user, NetWorthItemKind::Investment);

    nwValue($year, $investment, 1, 900_000);

    $this->actingAs($user)->delete(route('net-worth-items.destroy', $investment));

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(fn ($page) => $page->where('current.assetsCents', 0));
});

it('still lists a retired holding so it can be seen', function (): void {
    [$user] = userWithYear();

    $item = nwHolding($user, NetWorthItemKind::Investment);

    $this->actingAs($user)->delete(route('net-worth-items.destroy', $item));

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(function ($page) use ($item): void {
            $listed = collect($page->toArray()['props']['items'])->firstWhere('id', $item->id);

            expect($listed['isActive'])->toBeFalse();
        });
});

it('offers every kind of holding', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertInertia(fn ($page) => $page
            ->has('kinds', 5)
            ->where('kinds.0.value', NetWorthItemKind::Cash->value));
});

it('needs a signed-in user', function (): void {
    $this->get(route('net-worth.index'))->assertRedirect(route('login'));
});

it('lives under the goals hub and redirects from where it used to be', function (): void {
    [$user] = userWithYear();

    expect(route('net-worth.index', absolute: false))->toBe('/goals/net-worth');

    $this->actingAs($user)
        ->get('/net-worth')
        ->assertRedirect(route('net-worth.index'));
});
