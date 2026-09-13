<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Goal;

/**
 * Puts a goal away, or brings it back (GOAL-04).
 *
 * Archiving rather than deleting: a goal that was reached is part of what the user did,
 * and a goal abandoned may be picked up again. Nothing about a goal is worth destroying.
 */
final readonly class ArchiveGoal
{
    public function handle(Goal $goal, bool $archived = true): Goal
    {
        // archived_at is not mass-assignable: putting a goal away is a deliberate act,
        // never something a form field can do on its own.
        $goal->forceFill(['archived_at' => $archived ? now() : null])->save();

        return $goal;
    }
}
