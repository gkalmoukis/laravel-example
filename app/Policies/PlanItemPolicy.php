<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Ownership runs through the year the item belongs to (USR-01).
 */
final readonly class PlanItemPolicy
{
    public function view(User $user, PlanItem $planItem): Response
    {
        return $this->owns($user, $planItem);
    }

    public function update(User $user, PlanItem $planItem): Response
    {
        return $this->owns($user, $planItem);
    }

    /**
     * Generated items are removed by removing their source — the salary arrangement or
     * the subscription — never directly, or the next rebuild would bring them back.
     */
    public function delete(User $user, PlanItem $planItem): Response
    {
        if ($user->id !== $planItem->financialYear->user_id) {
            return Response::denyAsNotFound();
        }

        return $planItem->isManual()
            ? Response::allow()
            : Response::deny('This item comes from your salary or a subscription. Change it there instead.');
    }

    private function owns(User $user, PlanItem $planItem): Response
    {
        return $user->id === $planItem->financialYear->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
