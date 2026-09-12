<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Actions\DeactivateAccount;
use App\Actions\UpdateAccount;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AccountController
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/accounts', [
            // Scoped through the relationship, never Account::query(), so one user's
            // records can never appear in another's list (USR-02).
            'accounts' => $user->accounts()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'isActive' => $account->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(StoreAccountRequest $request, #[CurrentUser] User $user, CreateAccount $action): RedirectResponse
    {
        $action->handle($user, $request->validated());

        return to_route('accounts.index')->with('status', 'Account added.');
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccount $action): RedirectResponse
    {
        Gate::authorize('update', $account);

        $action->handle($account, $request->validated());

        return to_route('accounts.index')->with('status', 'Account updated.');
    }

    public function destroy(Account $account, DeactivateAccount $action): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $action->handle($account);

        return to_route('accounts.index')->with('status', 'Account deactivated.');
    }
}
