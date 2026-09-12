<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ResolveSelectedYear;
use App\Models\FinancialYear;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Collection;
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

    public function __construct(private readonly ResolveSelectedYear $resolveSelectedYear) {}

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
        $user = $request->user();
        $years = $this->years($user);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'preferences' => $this->preferences($request),
            // Server-derived abilities. The frontend only hides UI with these; every
            // route is still guarded by its policy (ARCH-04).
            'abilities' => [
                'canInvite' => $request->user()?->can('create', Invitation::class) ?? false,
            ],
            'years' => $years
                ->map(fn (FinancialYear $year): array => [
                    'year' => $year->year,
                    'isSetupComplete' => $year->isSetupComplete(),
                ])
                ->all(),
            'selectedYear' => $this->selectedYear($user, $years, $request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The year every screen is currently about, or nothing for a guest (YEAR-07).
     *
     * @param  Collection<int, FinancialYear>  $years
     */
    private function selectedYear(?User $user, Collection $years, Request $request): ?int
    {
        if (! $user instanceof User) {
            return null;
        }

        return $this->resolveSelectedYear->handle($user, $years, $this->requestedYear($request));
    }

    /**
     * The user's years, newest first, for the switcher in the top bar (YEAR-07).
     *
     * @return Collection<int, FinancialYear>
     */
    private function years(?User $user): Collection
    {
        if (! $user instanceof User) {
            return new Collection();
        }

        return $user->financialYears()->orderByDesc('year')->get();
    }

    /**
     * The year the current address is asking for, if any.
     *
     * Year-scoped routes bind it to a model; everything else may still ask for one in the
     * query string, which is how a link from a report reaches the transaction list already
     * pointed at the right year.
     */
    private function requestedYear(Request $request): ?int
    {
        $parameter = $request->route('year');

        if ($parameter instanceof FinancialYear) {
            return $parameter->year;
        }

        if (is_string($parameter) && ctype_digit($parameter)) {
            return (int) $parameter;
        }

        $query = $request->query('year');

        return is_string($query) && ctype_digit($query) ? (int) $query : null;
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
