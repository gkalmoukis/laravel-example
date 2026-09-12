<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * See AccountPolicy: another user's record is reported as missing, never as forbidden
 * (USR-02, USR-05).
 *
 * Goals get their screens in milestone 5. The policy exists now because provisioning
 * creates the emergency fund goal, and no record should be reachable without one.
 */
final readonly class GoalPolicy
{
    public function view(User $user, Goal $goal): Response
    {
        return $this->owns($user, $goal);
    }

    public function update(User $user, Goal $goal): Response
    {
        return $this->owns($user, $goal);
    }

    /**
     * The emergency fund goal always exists and cannot be removed (GOAL-01).
     */
    public function delete(User $user, Goal $goal): Response
    {
        if ($user->id !== $goal->user_id) {
            return Response::denyAsNotFound();
        }

        return $goal->type === GoalType::EmergencyFund
            ? Response::deny('The emergency fund goal cannot be deleted.')
            : Response::allow();
    }

    private function owns(User $user, Goal $goal): Response
    {
        return $user->id === $goal->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
