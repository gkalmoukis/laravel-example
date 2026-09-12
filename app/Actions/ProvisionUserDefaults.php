<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AccountType;
use App\Enums\GoalType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives a brand new account everything it needs to be usable immediately: preferences,
 * a starting set of categories, somewhere to record cash, and an emergency fund goal
 * (USR-04).
 *
 * Runs inside the invitation acceptance transaction, and is idempotent so seeders and
 * repeated runs are harmless.
 */
final readonly class ProvisionUserDefaults
{
    /**
     * Income categories, in display order.
     *
     * @var list<array{name: string, key?: string}>
     */
    private const array INCOME_CATEGORIES = [
        ['name' => 'Salary', 'key' => Category::KEY_SALARY],
        ['name' => 'Overtime'],
        ['name' => 'Christmas Bonus', 'key' => Category::KEY_CHRISTMAS_BONUS],
        ['name' => 'Easter Bonus', 'key' => Category::KEY_EASTER_BONUS],
        ['name' => 'Vacation Allowance', 'key' => Category::KEY_VACATION_ALLOWANCE],
        ['name' => 'Business Distributions'],
        ['name' => 'Freelance & Projects'],
        ['name' => 'Other Income'],
        ['name' => 'Extraordinary Income'],
    ];

    /**
     * Expense categories, in display order. "essential" feeds the emergency fund target;
     * "irregular" is the default for plan items created in the category.
     *
     * @var list<array{name: string, key?: string, essential?: bool, irregular?: bool, children?: list<string>}>
     */
    private const array EXPENSE_CATEGORIES = [
        ['name' => 'Housing', 'essential' => true],
        ['name' => 'Food & Groceries', 'essential' => true, 'children' => ['Supermarket', "Farmers' market (Laiki)"]],
        ['name' => 'Phone & Internet', 'essential' => true],
        ['name' => 'Utilities', 'essential' => true, 'children' => ['Electricity', 'Water']],
        ['name' => 'Transportation', 'essential' => true],
        ['name' => 'Personal Care & Health', 'essential' => true],
        ['name' => 'Subscriptions', 'key' => Category::KEY_SUBSCRIPTIONS],
        ['name' => 'Dining Out', 'children' => ['Coffee', 'Restaurant']],
        ['name' => 'AADE (Taxes)', 'irregular' => true],
        ['name' => 'Miscellaneous'],
        ['name' => 'Holidays', 'irregular' => true],
        ['name' => 'Annual Insurance', 'irregular' => true],
        ['name' => 'Car Expenses', 'irregular' => true],
        ['name' => 'Large Purchases', 'irregular' => true],
        ['name' => 'Other Irregular Expenses', 'irregular' => true],
    ];

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $cash = $this->provisionCashAccount($user);

            $this->provisionPreferences($user, $cash);
            $this->provisionCategories($user);
            $this->provisionEmergencyFundGoal($user);
        });
    }

    private function provisionCashAccount(User $user): Account
    {
        // Creating through the relationship sets the owner without mass assignment.
        return $user->accounts()->firstOrCreate(
            ['name' => 'Cash'],
            ['type' => AccountType::Cash, 'is_active' => true, 'sort_order' => 0],
        );
    }

    private function provisionPreferences(User $user, Account $cash): void
    {
        $user->preference()->firstOrCreate(
            [],
            // Pre-selecting the only account that exists saves a step on the first
            // transaction; the user can change or clear it in settings.
            ['default_account_id' => $cash->id],
        );
    }

    private function provisionCategories(User $user): void
    {
        $sortOrder = 0;

        foreach (self::INCOME_CATEGORIES as $category) {
            $this->provisionCategory($user, TransactionType::Income, $category, $sortOrder++);
        }

        $sortOrder = 0;

        foreach (self::EXPENSE_CATEGORIES as $category) {
            $parent = $this->provisionCategory($user, TransactionType::Expense, $category, $sortOrder++);

            $childOrder = 0;

            foreach ($category['children'] ?? [] as $child) {
                $user->categories()->firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $child],
                    ['type' => TransactionType::Expense, 'sort_order' => $childOrder++],
                );
            }
        }
    }

    /**
     * @param  array{name: string, key?: string, essential?: bool, irregular?: bool, children?: list<string>}  $category
     */
    private function provisionCategory(User $user, TransactionType $type, array $category, int $sortOrder): Category
    {
        $model = $user->categories()->firstOrCreate(
            ['parent_id' => null, 'name' => $category['name']],
            [
                'type' => $type,
                'is_essential' => $category['essential'] ?? false,
                'is_irregular' => $category['irregular'] ?? false,
                'sort_order' => $sortOrder,
            ],
        );

        if (isset($category['key']) && $model->system_key === null) {
            $model->forceFill(['system_key' => $category['key']])->save();
        }

        return $model;
    }

    private function provisionEmergencyFundGoal(User $user): void
    {
        $user->goals()->firstOrCreate(
            ['type' => GoalType::EmergencyFund],
            [
                'name' => 'Emergency Fund',
                // Left null so the target is computed from essential expenses until the
                // user sets a custom amount.
                'target_amount_cents' => null,
                'target_is_custom' => false,
            ],
        );
    }
}
