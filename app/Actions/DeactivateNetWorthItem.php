<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\NetWorthItem;

/**
 * Retires a holding the user no longer has (NW-02).
 *
 * Deactivated rather than deleted: the values recorded against it are part of what their
 * net worth was at the time, and removing the holding would rewrite that history. A
 * retired holding simply stops counting from now on.
 */
final readonly class DeactivateNetWorthItem
{
    public function handle(NetWorthItem $item, bool $active = false): NetWorthItem
    {
        $item->update(['is_active' => $active]);

        return $item;
    }
}
