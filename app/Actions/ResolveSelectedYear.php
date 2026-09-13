<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Which year the app is currently showing (YEAR-07).
 *
 * Year-scoped screens carry the year in their address, but the transaction list, goals,
 * net worth and subscriptions do not. Without somewhere to keep the choice, stepping from a
 * 2026 plan to the transaction list would silently snap back to the present, so an explicit
 * year is remembered and carries across the screens that cannot say it themselves.
 *
 * A first visit lands on the current calendar year when the user has planned it, because
 * that is the year they are living in; otherwise on their most recent one.
 */
final readonly class ResolveSelectedYear
{
    public const string SESSION_KEY = 'selected_year';

    /**
     * @param  Collection<int, FinancialYear>  $years
     */
    public function handle(User $user, Collection $years, ?int $requested): ?int
    {
        $available = $years->map(fn (FinancialYear $year): int => $year->year)->all();

        if ($available === []) {
            return null;
        }

        // An explicit year always wins, and is what later screens fall back to.
        if ($requested !== null && in_array($requested, $available, true)) {
            Session::put(self::SESSION_KEY, $requested);

            return $requested;
        }

        $remembered = Session::get(self::SESSION_KEY);

        if (is_int($remembered) && in_array($remembered, $available, true)) {
            return $remembered;
        }

        $current = $user->today()->year;

        return in_array($current, $available, true) ? $current : max($available);
    }
}
