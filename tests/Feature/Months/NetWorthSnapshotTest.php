<?php

declare(strict_types=1);

use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\NetWorthItem;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;

function holdingNamed(User $user, NetWorthItemKind $kind): NetWorthItem
{
    return $user->netWorthItems()->where('kind', $kind)->firstOrFail();
}

function snapshotValue(FinancialYear $year, NetWorthItem $item, int $month): ?int
{
    return NetWorthSnapshot::query()
        ->where('financial_year_id', $year->id)
        ->where('net_worth_item_id', $item->id)
        ->where('month', $month)
        ->first()?->value_cents->cents;
}

it('records what each holding was worth', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);
    $investments = holdingNamed($user, NetWorthItemKind::Investment);

    $this->actingAs($user)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
            'holdings' => [
                ['id' => $cash->id, 'amount' => '5.000,00'],
                ['id' => $investments->id, 'amount' => '12.345,67'],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(snapshotValue($year, $cash, 3))->toBe(500_000)
        ->and(snapshotValue($year, $investments, 3))->toBe(1_234_567);
});

it('replaces a value already recorded for that month', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    foreach (['1.000,00', '2.000,00'] as $amount) {
        $this->actingAs($user)->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
            'holdings' => [['id' => $cash->id, 'amount' => $amount]],
        ]);
    }

    expect(snapshotValue($year, $cash, 3))->toBe(200_000)
        ->and(NetWorthSnapshot::query()
            ->where('financial_year_id', $year->id)
            ->where('net_worth_item_id', $cash->id)
            ->where('month', 3)
            ->count())->toBe(1);
});

it('keeps each month separate', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    $this->actingAs($user)->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
        'holdings' => [['id' => $cash->id, 'amount' => '1.000,00']],
    ]);

    $this->actingAs($user)->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 4]), [
        'holdings' => [['id' => $cash->id, 'amount' => '2.000,00']],
    ]);

    expect(snapshotValue($year, $cash, 3))->toBe(100_000)
        ->and(snapshotValue($year, $cash, 4))->toBe(200_000)
        // Month 0 is the opening position and is written elsewhere.
        ->and(snapshotValue($year, $cash, NetWorthSnapshot::OPENING_MONTH))->toBe(0);
});

it('refuses an amount that is not a number', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    $this->actingAs($user)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
            'holdings' => [['id' => $cash->id, 'amount' => 'quite a lot']],
        ])
        ->assertSessionHasErrors(['holdings.0.amount' => 'Enter an amount like 1.234,56.']);

    expect(snapshotValue($year, $cash, 3))->toBeNull();
});

it('ignores a holding belonging to someone else', function (): void {
    [$user, $year] = userWithYear();
    [$other, $otherYear] = userWithYear(2026);

    $mine = holdingNamed($user, NetWorthItemKind::Cash);
    $theirs = holdingNamed($other, NetWorthItemKind::Cash);

    $this->actingAs($user)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
            'holdings' => [
                ['id' => $mine->id, 'amount' => '100,00'],
                ['id' => $theirs->id, 'amount' => '999,00'],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(snapshotValue($year, $mine, 3))->toBe(10_000)
        ->and(snapshotValue($otherYear, $theirs, 3))->toBeNull();
});

it('reports another user year as missing', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $cash = holdingNamed($intruder, NetWorthItemKind::Cash);

    $this->actingAs($intruder)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
            'holdings' => [['id' => $cash->id, 'amount' => '1,00']],
        ])
        ->assertNotFound();

    expect($owner->financialYears()->where('year', 2027)->exists())->toBeTrue();
});

it('refuses a month that does not exist', function (): void {
    [$user] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    $this->actingAs($user)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 13]), [
            'holdings' => [['id' => $cash->id, 'amount' => '1,00']],
        ])
        ->assertNotFound();
});

it('refuses a request with no holdings at all', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [])
        ->assertSessionHasErrors('holdings');
});

it('skips rows that are missing what they need', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    $this->actingAs($user)
        ->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 3]), [
            'holdings' => [
                ['id' => $cash->id, 'amount' => '50,00'],
                ['nonsense' => true],
            ],
        ])
        ->assertSessionHasErrors();

    expect(snapshotValue($year, $cash, 3))->toBeNull();
});

it('offers the month form the last figures the user gave', function (): void {
    [$user] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    $this->actingAs($user)->patch(route('net-worth-snapshots.update', ['year' => 2027, 'month' => 2]), [
        'holdings' => [['id' => $cash->id, 'amount' => '750,00']],
    ]);

    // March was never filled in, so it starts from February rather than from nothing.
    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page) use ($cash): void {
            $holding = collect($page->toArray()['props']['holdings'])
                ->firstWhere('id', $cash->id);

            expect($holding['valueCents'])->toBe(75_000)
                ->and($holding['isLiquid'])->toBeTrue();
        });
});

it('leaves retired holdings off the form', function (): void {
    [$user] = userWithYear();

    $investments = holdingNamed($user, NetWorthItemKind::Investment);
    $investments->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 3]))
        ->assertInertia(function ($page) use ($investments): void {
            $ids = collect($page->toArray()['props']['holdings'])->pluck('id');

            expect($ids)->not->toContain($investments->id);
        });
});

it('shows what the transactions say the liquid balance should be', function (): void {
    [$user, $year] = userWithYear();

    $cash = holdingNamed($user, NetWorthItemKind::Cash);

    NetWorthSnapshot::query()
        ->where('financial_year_id', $year->id)
        ->where('net_worth_item_id', $cash->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 100_000]);

    $housing = $user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => $housing->type,
        'category_id' => $housing->id,
        'occurred_on' => '2027-01-05',
        'amount_cents' => 30_000,
    ]);

    $this->actingAs($user)
        ->get(route('months.show', ['year' => 2027, 'month' => 1]))
        ->assertInertia(fn ($page) => $page->where('liquidClosingCents', 70_000));
});
