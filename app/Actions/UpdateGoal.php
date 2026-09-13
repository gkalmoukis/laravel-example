<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GoalType;
use App\Models\Goal;

/**
 * Changes a goal (GOAL-01, GOAL-03).
 *
 * The type is fixed at creation: it decides where the current amount comes from, and
 * changing it would silently reinterpret a figure the user entered by hand as one derived
 * from their forecast, or the other way round.
 *
 * The current amount is only the user's to set on goals nothing else tracks. For the
 * emergency fund and a year end balance the application already knows the answer, so a
 * figure typed here would be overwritten the moment anything else changed (GOAL-03).
 */
final readonly class UpdateGoal
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Goal $goal, array $attributes): Goal
    {
        if ($this->isTracked($goal)) {
            unset($attributes['current_amount_cents']);
        }

        $goal->update($attributes);

        return $goal;
    }

    private function isTracked(Goal $goal): bool
    {
        return in_array($goal->type, [GoalType::EmergencyFund, GoalType::YearEndBalance], true);
    }
}
