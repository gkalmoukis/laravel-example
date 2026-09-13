<?php

declare(strict_types=1);

use App\Enums\GoalType;
use App\Models\Goal;
use App\ValueObjects\Money;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

it('adds a goal and sees where it stands', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->visit('/goals')
        ->click('@add-goal')
        ->fill('name', 'New bike')
        ->fill('target_amount', '1.500,00')
        ->fill('monthly_contribution', '150,00')
        ->click('@save-goal')
        ->assertSee('New bike')
        ->assertSee('1.500,00')
        ->assertNoJavascriptErrors();
});

it('updates what has been saved towards a goal', function (): void {
    $user = planningUser();

    $goal = Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'Sofa',
        'target_amount_cents' => Money::fromCents(100_000),
        'current_amount_cents' => Money::zero(),
    ]);

    $this->actingAs($user)
        ->visit('/goals')
        ->click('@edit-'.$goal->id)
        ->fill('#current-'.$goal->id, '400,00')
        ->click('@save-'.$goal->id)
        ->assertSee('400,00')
        ->assertNoJavascriptErrors();
});

it('says which goals keep themselves up to date', function (): void {
    [$user] = userWithYear((int) date('Y'));

    // A read-only figure with no explanation reads as a broken input (GOAL-03).
    $this->actingAs($user)
        ->visit('/goals')
        ->assertSee('From what you have set aside')
        ->assertNoJavascriptErrors();
});

it('archives a goal and brings it back', function (): void {
    $user = planningUser();

    $goal = Goal::factory()->for($user)->create([
        'type' => GoalType::Purchase,
        'name' => 'Old plan',
        'target_amount_cents' => Money::fromCents(100_000),
    ]);

    $page = $this->actingAs($user)->visit('/goals');

    $page->click('@archive-'.$goal->id)
        ->assertSee('1 archived')
        ->assertNoJavascriptErrors();

    $page->click('@toggle-archived')
        ->assertSee('Old plan')
        ->click('@restore-'.$goal->id)
        ->assertNoJavascriptErrors();
});

it('reads the goals on a phone', function (): void {
    $user = planningUser();

    $this->actingAs($user)
        ->visit('/goals')
        ->on()->mobile()
        ->assertSee('Goals')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('moves between goals, the emergency fund and net worth from one tab row', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $this->actingAs($user)
        ->visit('/goals')
        ->assertSee('Goals')
        ->click('@tab-emergency-fund')
        ->assertPathIs('/goals/emergency-fund')
        ->click('@tab-net-worth')
        ->assertPathIs('/goals/net-worth')
        ->assertSee('Net worth')
        ->assertNoJavascriptErrors();
});
