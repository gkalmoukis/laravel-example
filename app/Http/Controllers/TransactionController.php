<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\DeleteTransaction;
use App\Actions\UpdateTransaction;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class TransactionController
{
    public function store(StoreTransactionRequest $request, CreateTransaction $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $action->handle($user, $request->transactionAttributes(), $request->amount(), $request->occurredOn(), $request->reopensMonth());

        return back()->with('status', 'Transaction saved.');
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $action): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $action->handle($transaction, $request->transactionAttributes(), $request->amount(), $request->occurredOn(), $request->reopensMonth());

        return back()->with('status', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction, DeleteTransaction $action): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $action->handle($transaction, request()->boolean('reopen_month'));

        return back()->with('status', 'Transaction deleted.');
    }
}
