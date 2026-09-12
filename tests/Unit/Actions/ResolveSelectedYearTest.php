<?php

declare(strict_types=1);

use App\Actions\ResolveSelectedYear;
use App\Models\FinancialYear;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;

/**
 * @param  list<int>  $years
 * @return Collection<int, FinancialYear>
 */
function yearsFor(User $user, array $years): Collection
{
    foreach ($years as $year) {
        FinancialYear::factory()->for($user)->create(['year' => $year]);
    }

    return $user->financialYears()->orderByDesc('year')->get();
}

it('has nothing to select when the user has no years', function (): void {
    $user = User::factory()->create();

    $selected = resolve(ResolveSelectedYear::class)->handle($user, yearsFor($user, []), null);

    expect($selected)->toBeNull();
});

it('takes the year the address asks for and remembers it', function (): void {
    $user = User::factory()->create();

    $selected = resolve(ResolveSelectedYear::class)->handle($user, yearsFor($user, [2025, 2026]), 2025);

    expect($selected)->toBe(2025)
        ->and(Session::get(ResolveSelectedYear::SESSION_KEY))->toBe(2025);
});

it('ignores a year the user does not have', function (): void {
    $user = User::factory()->create();

    $selected = resolve(ResolveSelectedYear::class)->handle($user, yearsFor($user, [2025]), 2099);

    expect($selected)->toBe(2025)
        ->and(Session::get(ResolveSelectedYear::SESSION_KEY))->toBeNull();
});

it('carries the remembered year to a screen that cannot name one', function (): void {
    $user = User::factory()->create();
    $years = yearsFor($user, [2025, 2026]);

    Session::put(ResolveSelectedYear::SESSION_KEY, 2025);

    expect(resolve(ResolveSelectedYear::class)->handle($user, $years, null))->toBe(2025);
});

it('forgets a remembered year the user no longer has', function (): void {
    $user = User::factory()->create();
    $years = yearsFor($user, [2026]);

    Session::put(ResolveSelectedYear::SESSION_KEY, 1999);

    expect(resolve(ResolveSelectedYear::class)->handle($user, $years, null))->toBe(2026);
});

it('ignores a remembered value that is not a year at all', function (): void {
    $user = User::factory()->create();
    $years = yearsFor($user, [2026]);

    Session::put(ResolveSelectedYear::SESSION_KEY, 'last one');

    expect(resolve(ResolveSelectedYear::class)->handle($user, $years, null))->toBe(2026);
});

it('starts on the year the user is living in', function (): void {
    $user = User::factory()->create();
    $current = $user->today()->year;

    $years = yearsFor($user, [$current - 1, $current]);

    expect(resolve(ResolveSelectedYear::class)->handle($user, $years, null))->toBe($current);
});

it('falls back to the latest year when the present one was never planned', function (): void {
    $user = User::factory()->create();
    $current = $user->today()->year;

    $years = yearsFor($user, [$current - 3, $current - 2]);

    expect(resolve(ResolveSelectedYear::class)->handle($user, $years, null))->toBe($current - 2);
});

it('reads today in the user timezone rather than the server clock', function (): void {
    $user = User::factory()->create();

    UserPreference::factory()->for($user)->create(['timezone' => 'Pacific/Kiritimati']);

    // 31 December 22:00 UTC is already New Year's Day in Kiritimati, so the year the user
    // is living in is the next one.
    $this->travelTo('2026-12-31 22:00:00');

    expect($user->fresh()?->today()->year)->toBe(2027);
});
