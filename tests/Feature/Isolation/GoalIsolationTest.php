<?php

declare(strict_types=1);

use App\Enums\GoalType;
use App\Models\Goal;
use App\ValueObjects\Money;

/*
 * Another user's record is reported as missing rather than forbidden, so nothing leaks —
 * not even that it exists (USR-02, USR-03).
 */

it('reports another user goal as missing when updated', function (): void {
    $owner = planningUser();
    $intruder = planningUser();

    $goal = Goal::factory()->for($owner)->create([
        'type' => GoalType::Purchase,
        'name' => 'Theirs',
        'target_amount_cents' => Money::fromCents(100_000),
    ]);

    $this->actingAs($intruder)
        ->patch(route('goals.update', $goal), [
            'name' => 'Mine now',
            'target_amount' => '1,00',
        ])
        ->assertNotFound();

    expect($goal->refresh()->name)->toBe('Theirs');
});

it('reports another user goal as missing when archived', function (): void {
    $owner = planningUser();
    $intruder = planningUser();

    $goal = Goal::factory()->for($owner)->create([
        'type' => GoalType::Purchase,
        'name' => 'Theirs',
    ]);

    $this->actingAs($intruder)
        ->post(route('goal-archive.store', $goal))
        ->assertNotFound();

    expect($goal->refresh()->archived_at)->toBeNull();
});

it('shows each user only their own goals', function (): void {
    $owner = planningUser();
    $intruder = planningUser();

    Goal::factory()->for($owner)->create(['type' => GoalType::Purchase, 'name' => 'Theirs']);
    Goal::factory()->for($intruder)->create(['type' => GoalType::Purchase, 'name' => 'Mine']);

    $this->actingAs($intruder)
        ->get(route('goals.index'))
        ->assertInertia(function ($page): void {
            $names = collect($page->toArray()['props']['goals'])->pluck('name');

            expect($names)->toContain('Mine')
                ->and($names)->not->toContain('Theirs');
        });
});
