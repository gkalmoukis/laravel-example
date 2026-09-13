<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\FinancialYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootRouteBindings();
    }

    /**
     * Year-scoped routes carry the calendar year rather than an id, so the address bar
     * stays legible (YEAR-07).
     *
     * The lookup is scoped to the signed-in user, so another user's year resolves to
     * nothing and the route simply reports it missing, revealing neither its existence
     * nor anything about it (USR-02).
     */
    private function bootRouteBindings(): void
    {
        Route::bind('year', function (string $value): FinancialYear {
            abort_unless(ctype_digit($value), 404);

            return FinancialYear::query()
                ->where('user_id', Auth::id())
                ->where('year', (int) $value)
                ->firstOrFail();
        });
    }
}
