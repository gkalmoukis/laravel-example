<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GoalType;
use App\Enums\TransactionType;
use App\Models\Goal;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Everything the user can change about their emergency fund, in one go (EF-02).
 *
 * The four settings pull on each other: which categories are essential decides the
 * suggested target, how many months of cover multiplies it, a custom amount overrides it
 * entirely, and the contribution decides when it is reached. Saving them separately would
 * let the screen show a target computed from half-old settings, so they move together.
 */
final readonly class UpdateEmergencyFundSettings
{
    /**
     * @param  list<int>  $essentialCategoryIds
     */
    public function handle(
        User $user,
        int $monthsOfCover,
        array $essentialCategoryIds,
        ?Money $customTarget,
        ?Money $monthlyContribution,
    ): Goal {
        return DB::transaction(function () use ($user, $monthsOfCover, $essentialCategoryIds, $customTarget, $monthlyContribution): Goal {
            // firstOrCreate rather than the read-only preferences() accessor: this is a
            // write, so it needs a row that exists rather than one carrying defaults.
            $user->preference()->firstOrCreate([])->update([
                'emergency_fund_months' => $monthsOfCover,
            ]);

            $this->markEssential($user, $essentialCategoryIds);

            $goal = $user->goals()
                ->where('type', GoalType::EmergencyFund)
                ->firstOrFail();

            $goal->update([
                // A custom target and a computed one are a choice between two, not two
                // settings: clearing the amount returns to the computed figure.
                'target_is_custom' => $customTarget instanceof Money,
                'target_amount_cents' => $customTarget,
                'monthly_contribution_cents' => $monthlyContribution,
            ]);

            return $goal;
        });
    }

    /**
     * Sets exactly the categories the user ticked, and unsets the rest.
     *
     * Only expense categories can be essential: the flag answers "would this still have
     * to be paid", which is not a question about income (CAT-06).
     *
     * @param  list<int>  $essentialCategoryIds
     */
    private function markEssential(User $user, array $essentialCategoryIds): void
    {
        $expenses = $user->categories()
            ->where('type', TransactionType::Expense)
            ->whereNull('parent_id');

        (clone $expenses)->whereIn('id', $essentialCategoryIds)->update(['is_essential' => true]);
        (clone $expenses)->whereNotIn('id', $essentialCategoryIds)->update(['is_essential' => false]);
    }
}
