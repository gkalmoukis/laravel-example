<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\ValueObjects\Money;

it('reads a cents column as money', function (): void {
    $goal = Goal::factory()->for(User::factory())->create(['target_amount_cents' => 123456]);

    expect($goal->refresh()->target_amount_cents)->toBeInstanceOf(Money::class)
        ->and($goal->target_amount_cents?->cents)->toBe(123456);
});

it('writes money back as cents', function (): void {
    $goal = Goal::factory()->for(User::factory())->create();

    $goal->update(['target_amount_cents' => Money::fromCents(98765)]);

    expect($goal->refresh()->target_amount_cents?->cents)->toBe(98765);
});

it('accepts a plain integer as well', function (): void {
    $goal = Goal::factory()->for(User::factory())->create();

    $goal->update(['target_amount_cents' => 4200]);

    expect($goal->refresh()->target_amount_cents?->cents)->toBe(4200);
});

it('round-trips a null amount', function (): void {
    $goal = Goal::factory()->for(User::factory())->create(['target_amount_cents' => null]);

    expect($goal->refresh()->target_amount_cents)->toBeNull();

    $goal->update(['monthly_contribution_cents' => null]);

    expect($goal->refresh()->monthly_contribution_cents)->toBeNull();
});
