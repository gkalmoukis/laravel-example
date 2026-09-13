<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use App\Models\MonthClosure;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Files many transactions under one category at once (TXL-04).
 *
 * Refiling a batch is how a year's worth of miscategorised history gets put right, so the
 * rules that guard a single edit still apply to every row — but one bad row does not sink
 * the batch. Anything that cannot be moved is left exactly as it was and counted, so the
 * user is told what did not happen rather than having to compare before and after.
 */
final readonly class RecategorizeTransactions
{
    /**
     * @param  list<int>  $transactionIds
     * @return array{updated: int, skippedCompleted: int, skippedType: int}
     */
    public function handle(User $user, array $transactionIds, Category $category, ?Category $subcategory): array
    {
        return DB::transaction(function () use ($user, $transactionIds, $category, $subcategory): array {
            $transactions = $user->transactions()->whereKey($transactionIds)->get();

            $completeMonths = $this->completeMonths($user);

            $movable = [];
            $skippedCompleted = 0;
            $skippedType = 0;

            foreach ($transactions as $transaction) {
                // A category records a direction; moving an expense into an income
                // category would silently reverse it in every figure (TXV-01).
                if ($transaction->type !== $category->type) {
                    $skippedType++;

                    continue;
                }

                // A finished month is left alone rather than reopened: reopening several
                // months as a side effect of one bulk action is not something the user
                // asked for (TXV-02).
                if (in_array($this->monthKey($transaction), $completeMonths, true)) {
                    $skippedCompleted++;

                    continue;
                }

                $movable[] = $transaction->id;
            }

            if ($movable !== []) {
                Transaction::query()->whereKey($movable)->update([
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory?->id,
                ]);
            }

            return [
                'updated' => count($movable),
                'skippedCompleted' => $skippedCompleted,
                'skippedType' => $skippedType,
            ];
        });
    }

    /**
     * Every finished month of the user's, read once rather than per row.
     *
     * @return list<string>
     */
    private function completeMonths(User $user): array
    {
        $closures = MonthClosure::query()
            ->whereNotNull('completed_at')
            ->whereHas('financialYear', fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->with('financialYear')
            ->get();

        $months = [];

        foreach ($closures as $closure) {
            $months[] = sprintf('%d-%d', $closure->financialYear->year, $closure->month);
        }

        return $months;
    }

    private function monthKey(Transaction $transaction): string
    {
        return sprintf('%d-%d', $transaction->occurred_on->year, $transaction->occurred_on->month);
    }
}
