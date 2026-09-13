<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\NetWorthItem;

/**
 * Renames a holding, or changes what kind of thing it is (NW-02).
 *
 * The kind may change — an account repurposed as the emergency fund is an ordinary thing
 * to do — and because net worth is recomputed on every request, the history moves with
 * it rather than being split between two kinds.
 */
final readonly class UpdateNetWorthItem
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(NetWorthItem $item, array $attributes): NetWorthItem
    {
        $item->update($attributes);

        return $item;
    }
}
