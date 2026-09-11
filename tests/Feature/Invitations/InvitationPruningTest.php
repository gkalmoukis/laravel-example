<?php

declare(strict_types=1);

use App\Models\Invitation;

it('prunes finished invitations once they are past the retention period', function (string $state): void {
    config()->set('invitations.prune_after_days', 30);

    $stale = Invitation::factory()->{$state}()->create();
    $stale->forceFill(['updated_at' => now()->subDays(31)])->save();

    $this->artisan('model:prune', ['--model' => [Invitation::class]])->assertSuccessful();

    expect(Invitation::query()->whereKey($stale->id)->exists())->toBeFalse();
})->with(['accepted', 'revoked', 'expired']);

it('keeps finished invitations that are still inside the retention period', function (): void {
    config()->set('invitations.prune_after_days', 30);

    $recent = Invitation::factory()->revoked()->create();

    $this->artisan('model:prune', ['--model' => [Invitation::class]])->assertSuccessful();

    expect(Invitation::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('never prunes an invitation that is still pending', function (): void {
    config()->set('invitations.prune_after_days', 30);

    $pending = Invitation::factory()->create();
    $pending->forceFill(['updated_at' => now()->subYear()])->save();

    $this->artisan('model:prune', ['--model' => [Invitation::class]])->assertSuccessful();

    expect(Invitation::query()->whereKey($pending->id)->exists())->toBeTrue();
});
