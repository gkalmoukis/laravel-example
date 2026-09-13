<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateNetWorthItem;
use App\Actions\DeactivateNetWorthItem;
use App\Actions\UpdateNetWorthItem;
use App\Http\Requests\StoreNetWorthItemRequest;
use App\Http\Requests\UpdateNetWorthItemRequest;
use App\Models\NetWorthItem;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class NetWorthItemController
{
    public function store(StoreNetWorthItemRequest $request, #[CurrentUser] User $user, CreateNetWorthItem $action): RedirectResponse
    {
        $action->handle($user, $request->itemAttributes());

        return back()->with('status', 'Added to your net worth.');
    }

    public function update(UpdateNetWorthItemRequest $request, NetWorthItem $netWorthItem, UpdateNetWorthItem $action): RedirectResponse
    {
        Gate::authorize('update', $netWorthItem);

        $action->handle($netWorthItem, $request->itemAttributes());

        return back()->with('status', 'Updated.');
    }

    /**
     * Retires a holding rather than removing it: the values recorded against it are part
     * of what the user's net worth was at the time (NW-02).
     */
    public function destroy(NetWorthItem $netWorthItem, DeactivateNetWorthItem $action): RedirectResponse
    {
        Gate::authorize('delete', $netWorthItem);

        $action->handle($netWorthItem);

        return back()->with('status', 'No longer counted.');
    }
}
