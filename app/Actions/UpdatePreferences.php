<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\UserPreference;

final readonly class UpdatePreferences
{
    /**
     * Preferences are read fresh on every request, so a change takes effect on the next
     * page load without touching the session (PREF-04).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): UserPreference
    {
        $preference = $user->preference()->firstOrCreate([]);

        $preference->update($attributes);

        return $preference;
    }
}
