<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlanItem;

final readonly class DeletePlanItem
{
    /**
     * Plan items are genuinely deleted, unlike categories and accounts: nothing records
     * history against them, and their monthly amounts go with them by cascade.
     *
     * Generated items are not deleted directly — removing the salary arrangement or a
     * subscription is what removes those.
     */
    public function handle(PlanItem $planItem): void
    {
        $planItem->delete();
    }
}
