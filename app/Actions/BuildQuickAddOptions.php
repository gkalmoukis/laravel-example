<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;

/**
 * Everything quick add needs to open with sensible answers already filled in (TXQ-02, TXQ-04).
 *
 * The fifteen-second target is mostly won here rather than in the form: a category list that
 * puts the user's habits first, and an account already chosen, are what remove the taps.
 */
final readonly class BuildQuickAddOptions
{
    /**
     * How far back a habit counts. Long enough to cover a seasonal pattern, short enough
     * that last year's spending does not shape this week's list (TXQ-04).
     */
    private const int HABIT_DAYS = 90;

    private const int MOST_USED = 5;

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $categories = $user->categories()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories
                ->filter(fn (Category $category): bool => $category->isTopLevel())
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                    'subcategories' => $categories
                        ->filter(fn (Category $child): bool => $child->parent_id === $category->id)
                        ->map(fn (Category $child): array => ['id' => $child->id, 'name' => $child->name])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),

            'accounts' => $user->accounts()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name])
                ->all(),

            'defaultAccountId' => $this->defaultAccountId($user),

            'mostUsedCategoryIds' => [
                TransactionType::Expense->value => $this->mostUsed($user, TransactionType::Expense),
                TransactionType::Income->value => $this->mostUsed($user, TransactionType::Income),
            ],
        ];
    }

    /**
     * The account to preselect (ACC-02).
     *
     * Whatever the user reached for last, because the next entry is usually paid the same
     * way; then their stated default; then nothing rather than a guess.
     */
    private function defaultAccountId(User $user): ?int
    {
        $lastUsed = $user->transactions()
            ->whereNotNull('account_id')
            ->latest('created_at')
            ->latest('id')
            ->value('account_id');

        if (is_int($lastUsed)) {
            return $lastUsed;
        }

        return $user->preference->default_account_id ?? null;
    }

    /**
     * The categories this user actually reaches for, most used first (TXQ-04).
     *
     * @return list<int>
     */
    private function mostUsed(User $user, TransactionType $type): array
    {
        $rows = $user->transactions()
            ->where('type', $type)
            ->where('occurred_on', '>=', $user->today()->subDays(self::HABIT_DAYS)->toDateString())
            ->groupBy('category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(self::MOST_USED)
            ->get(['category_id']);

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = $row->category_id;
        }

        return $ids;
    }
}
