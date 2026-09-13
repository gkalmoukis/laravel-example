<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\User;

/**
 * Adds something the user is saving towards (GOAL-01).
 *
 * The emergency fund is not created here: every account is provisioned with exactly one,
 * and a second would make "the emergency fund" ambiguous. Its settings live on its own
 * screen (EF-02).
 */
final readonly class CreateGoal
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Goal
    {
        return $user->goals()->create([
            ...$attributes,
            'financial_year_id' => $this->yearFor($attributes),
        ]);
    }

    /**
     * A year end balance is measured against a year, so it keeps the one it was created
     * for. Every other kind of goal is not tied to a year at all, and carrying one would
     * make it look as though it were.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function yearFor(array $attributes): ?int
    {
        if (($attributes['type'] ?? null) !== GoalType::YearEndBalance->value) {
            return null;
        }

        $yearId = $attributes['financial_year_id'] ?? null;

        return is_numeric($yearId) ? (int) $yearId : null;
    }
}
