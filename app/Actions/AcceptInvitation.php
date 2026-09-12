<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final readonly class AcceptInvitation
{
    public function __construct(private ProvisionUserDefaults $provisionDefaults) {}

    /**
     * Creates the account and consumes the invitation in one transaction (INV-06).
     *
     * The account is created already verified: the only way to reach this point is a
     * single-use link delivered to that address, which proves the user controls it
     * (VER-02). Logging in is left to the caller so the action stays usable from tests
     * and the console.
     */
    public function handle(Invitation $invitation, string $name, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'is_admin' => $invitation->is_admin,
            ])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            // Same transaction as the account itself, so a new user never exists without
            // their categories, preferences and emergency fund goal (USR-04).
            $this->provisionDefaults->handle($user);

            event(new Registered($user));

            return $user;
        });
    }
}
