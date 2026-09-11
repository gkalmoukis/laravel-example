<?php

declare(strict_types=1);

use App\Actions\AcceptInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an account that is already verified', function (): void {
    $invitation = Invitation::factory()->create(['email' => 'invitee@example.test']);

    $user = resolve(AcceptInvitation::class)->handle($invitation, 'Invited Person', 'StrongPassword1234');

    expect($user->email)->toBe('invitee@example.test')
        ->and($user->name)->toBe('Invited Person')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->is_admin)->toBeFalse()
        ->and(Hash::check('StrongPassword1234', $user->password))->toBeTrue();
});

it('consumes the invitation', function (): void {
    $invitation = Invitation::factory()->create();

    resolve(AcceptInvitation::class)->handle($invitation, 'Invited Person', 'StrongPassword1234');

    expect($invitation->refresh()->accepted_at)->not->toBeNull()
        ->and($invitation->isPending())->toBeFalse();
});

it('grants the admin flag when the invitation carries it', function (): void {
    $invitation = Invitation::factory()->grantingAdmin()->create();

    $user = resolve(AcceptInvitation::class)->handle($invitation, 'Invited Admin', 'StrongPassword1234');

    expect($user->is_admin)->toBeTrue();
});

it('takes the email from the invitation, never from the caller', function (): void {
    $invitation = Invitation::factory()->create(['email' => 'invited@example.test']);

    $user = resolve(AcceptInvitation::class)->handle($invitation, 'Invited Person', 'StrongPassword1234');

    expect($user->email)->toBe('invited@example.test')
        ->and(User::query()->count())->toBe(2);
});
