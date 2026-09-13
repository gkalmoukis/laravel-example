<?php

declare(strict_types=1);

use App\Enums\NetWorthItemKind;

/*
 * Another user's record is reported as missing rather than forbidden, so nothing leaks —
 * not even that it exists (USR-02, USR-03).
 */

it('reports another user holding as missing when renamed', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $item = $owner->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    $this->actingAs($intruder)
        ->patch(route('net-worth-items.update', $item), [
            'name' => 'Mine now',
            'kind' => NetWorthItemKind::Cash->value,
        ])
        ->assertNotFound();

    expect($item->refresh()->name)->not->toBe('Mine now');
});

it('reports another user holding as missing when retired', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $item = $owner->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

    $this->actingAs($intruder)
        ->delete(route('net-worth-items.destroy', $item))
        ->assertNotFound();

    expect($item->refresh()->is_active)->toBeTrue();
});

it('shows each user only their own holdings', function (): void {
    [$owner] = userWithYear();
    [$intruder] = userWithYear(2026);

    $owner->netWorthItems()->where('kind', NetWorthItemKind::Cash)->update(['name' => 'Their cash']);

    $this->actingAs($intruder)
        ->get(route('net-worth.index'))
        ->assertInertia(function ($page): void {
            $names = collect($page->toArray()['props']['items'])->pluck('name');

            expect($names)->not->toContain('Their cash');
        });
});
