<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecategorizeTransactions;
use App\Http\Requests\UpdateTransactionCategoryRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class TransactionCategoryController
{
    public function update(
        UpdateTransactionCategoryRequest $request,
        #[CurrentUser] User $user,
        RecategorizeTransactions $action,
    ): RedirectResponse {
        $result = $action->handle(
            $user,
            $request->transactionIds(),
            $request->category(),
            $request->subcategory(),
        );

        return back()->with('status', $this->summarise($result));
    }

    /**
     * Says what happened in one line, including what did not (TXL-04).
     *
     * Silence about skipped rows would leave the user believing a batch went through when
     * part of it did not, which is the one outcome worse than refusing the whole thing.
     *
     * @param  array{updated: int, skippedCompleted: int, skippedType: int}  $result
     */
    private function summarise(array $result): string
    {
        $sentence = sprintf(
            'Updated %d %s.',
            $result['updated'],
            $result['updated'] === 1 ? 'transaction' : 'transactions',
        );

        if ($result['skippedCompleted'] > 0) {
            $sentence .= sprintf(' Skipped %d in completed months.', $result['skippedCompleted']);
        }

        if ($result['skippedType'] > 0) {
            $sentence .= sprintf(
                ' Skipped %d that record money moving the other way.',
                $result['skippedType'],
            );
        }

        return $sentence;
    }
}
