<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootFortifyDefaults();
        $this->bootPasswordDefaults();
        $this->bootRateLimitingDefaults();
    }

    private function bootFortifyDefaults(): void
    {
        Fortify::twoFactorChallengeView(fn () => Inertia::render('user-two-factor-authentication-challenge/show'));
        Fortify::confirmPasswordView(fn () => Inertia::render('user-password-confirmation/create'));
    }

    /**
     * Password rules for every place a password is set: invitation acceptance, password
     * reset and password change (AUTH-04). Breach checking calls an external API, so it
     * runs in production only.
     */
    private function bootPasswordDefaults(): void
    {
        Password::defaults(function (): Password {
            $password = Password::min(12)->mixedCase()->numbers();

            return $this->app->isProduction()
                ? $password->uncompromised()
                : $password;
        });
    }

    private function bootRateLimitingDefaults(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->value().$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
