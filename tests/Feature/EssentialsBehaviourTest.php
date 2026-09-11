<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use NunoMaduro\Essentials\Configurables\ProhibitDestructiveCommands;

it('throws when reading an attribute that was not selected', function (): void {
    User::factory()->create();

    $user = User::query()->select('id')->firstOrFail();

    expect(fn (): mixed => $user->email)->toThrow(MissingAttributeException::class);
});

it('has lazy loading prevention switched on', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
});

it('eager loads relationships automatically rather than issuing a query per row', function (): void {
    Invitation::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach (Invitation::query()->get() as $invitation) {
        $invitation->inviter;
    }

    // One query for the invitations and one for their inviters, not one per row.
    expect(DB::getQueryLog())->toHaveCount(2);
});

it('keeps mass-assignment protection on', function (): void {
    expect(Model::isUnguarded())->toBeFalse()
        ->and(fn (): mixed => User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.test',
            'password' => 'StrongPassword1234',
            'is_admin' => true,
        ]))->toThrow(MassAssignmentException::class);
});

it('uses immutable dates everywhere', function (): void {
    expect(now())->toBeInstanceOf(CarbonImmutable::class)
        ->and(User::factory()->create()->created_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('blocks destructive console commands in production', function (): void {
    // Essentials wires the prohibition to the production environment at boot, so the
    // environment cannot be swapped mid-test. Assert the switch is configured on, and
    // that the mechanism it flips actually stops the command.
    expect(config()->boolean('essentials.'.ProhibitDestructiveCommands::class))->toBeTrue();

    DB::prohibitDestructiveCommands();

    try {
        // --force skips the confirmation prompt, leaving the prohibition to fail it.
        $this->artisan('migrate:fresh', ['--force' => true])->assertFailed();
    } finally {
        DB::prohibitDestructiveCommands(false);
    }
});

it('fails tests that make an unfaked outgoing request', function (): void {
    expect(fn (): mixed => Http::get('https://example.test'))
        ->toThrow(RuntimeException::class);
});
