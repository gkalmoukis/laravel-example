<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Invitation;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'preferences' => $this->preferences($request),
            // Server-derived abilities. The frontend only hides UI with these; every
            // route is still guarded by its policy (ARCH-04).
            'abilities' => [
                'canInvite' => $request->user()?->can('create', Invitation::class) ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The formatting preferences every page needs. Read fresh each request, so changing
     * them takes effect on the next page load without touching the session (PREF-04).
     *
     * Falls back to the documented defaults rather than null, so formatting never has to
     * be guarded at the call site.
     *
     * @return array<string, string>|null
     */
    private function preferences(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $preference = $user->preference;

        return [
            'currency' => $preference->currency ?? UserPreference::DEFAULT_CURRENCY,
            'formatLocale' => $preference->format_locale ?? UserPreference::DEFAULT_FORMAT_LOCALE,
            'timezone' => $preference->timezone ?? UserPreference::DEFAULT_TIMEZONE,
        ];
    }
}
