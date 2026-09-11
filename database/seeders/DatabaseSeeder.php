<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    /**
     * The local development account. These credentials are documented in the README and
     * exist only outside production, which the guard below enforces.
     */
    public const string ADMIN_EMAIL = 'admin@fin.test';

    public const string ADMIN_PASSWORD = 'Password1234';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed in production. Use app:invite instead.');

            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            ['name' => 'Local Admin', 'password' => self::ADMIN_PASSWORD],
        );

        $admin->forceFill([
            'email_verified_at' => now(),
            'is_admin' => true,
            'password' => Hash::make(self::ADMIN_PASSWORD),
        ])->save();

        // A pending, an expired and a revoked invitation, so every state on the
        // invitations screen is visible without having to create them by hand.
        Invitation::factory()->create([
            'email' => 'pending@fin.test',
            'invited_by' => $admin->id,
        ]);

        Invitation::factory()->expired()->create([
            'email' => 'expired@fin.test',
            'invited_by' => $admin->id,
        ]);

        Invitation::factory()->revoked()->create([
            'email' => 'revoked@fin.test',
            'invited_by' => $admin->id,
        ]);
    }
}
