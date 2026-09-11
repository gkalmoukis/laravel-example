<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;

final readonly class RevokeInvitation
{
    /**
     * Makes the emailed link unusable immediately (INV-04).
     */
    public function handle(Invitation $invitation): void
    {
        $invitation->forceFill(['revoked_at' => now()])->save();
    }
}
