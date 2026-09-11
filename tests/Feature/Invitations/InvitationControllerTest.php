<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\Notification;

it('lists invitations for an admin', function (): void {
    $admin = User::factory()->admin()->create();
    Invitation::factory()->create(['email' => 'pending@example.test']);

    $this->actingAs($admin)
        ->get(route('invitations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/index')
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.test')
            ->where('invitations.0.status', 'pending'));
});

it('refuses every invitation route to a non-admin', function (string $method, string $route): void {
    $user = User::factory()->create();
    $invitation = Invitation::factory()->create();

    $parameters = str_contains($route, 'index') || str_contains($route, 'invitations.store')
        ? []
        : ['invitation' => $invitation->id];

    $this->actingAs($user)
        ->call($method, route($route, $parameters), ['email' => 'someone@example.test'])
        ->assertForbidden();
})->with([
    ['get', 'invitations.index'],
    ['post', 'invitations.store'],
    ['delete', 'invitations.destroy'],
    ['post', 'invitation-resend.store'],
]);

it('sends an invitation', function (): void {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), ['email' => 'invitee@example.test'])
        ->assertRedirectToRoute('invitations.index');

    $invitation = Invitation::query()->where('email', 'invitee@example.test')->firstOrFail();

    expect($invitation->invited_by)->toBe($admin->id);

    Notification::assertSentTo($invitation, InvitationIssued::class);
});

it('normalises the address before checking it', function (): void {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($admin)
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), ['email' => '  TAKEN@Example.test '])
        ->assertSessionHasErrors('email');

    expect(Invitation::query()->count())->toBe(0);
});

it('rejects an address that already has an account', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($admin)
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), ['email' => 'taken@example.test'])
        ->assertSessionHasErrors('email');
});

it('revokes a pending invitation', function (): void {
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->create();

    $this->actingAs($admin)
        ->from(route('invitations.index'))
        ->delete(route('invitations.destroy', ['invitation' => $invitation->id]))
        ->assertRedirectToRoute('invitations.index');

    expect($invitation->refresh()->revoked_at)->not->toBeNull();
});

it('will not revoke or resend an invitation that is already finished', function (string $state): void {
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->{$state}()->create();

    $this->actingAs($admin)
        ->delete(route('invitations.destroy', ['invitation' => $invitation->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('invitation-resend.store', ['invitation' => $invitation->id]))
        ->assertForbidden();
})->with(['accepted', 'revoked']);

it('resends a pending invitation', function (): void {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->withToken('original')->create();

    $this->actingAs($admin)
        ->from(route('invitations.index'))
        ->post(route('invitation-resend.store', ['invitation' => $invitation->id]))
        ->assertRedirectToRoute('invitations.index');

    expect($invitation->refresh()->token_hash)->not->toBe(hash('sha256', 'original'));

    Notification::assertSentTo($invitation, InvitationIssued::class);
});
