<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'is_admin' => false,
            'invited_by' => User::factory()->admin(),
            'expires_at' => now()->addDays(config()->integer('invitations.expires_after_days')),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }

    public function grantingAdmin(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): self
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now()->subHour(),
        ]);
    }

    public function revoked(): self
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now()->subHour(),
        ]);
    }

    /**
     * Issues the invitation with a known plaintext token, so tests can build the link.
     */
    public function withToken(string $token): self
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => hash('sha256', $token),
        ]);
    }
}
