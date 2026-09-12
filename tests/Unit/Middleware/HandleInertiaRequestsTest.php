<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

it('shares app name from config', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('name')
        ->and($shared['name'])->toBe(config('app.name'));
});

it('shares null user when guest', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('auth')
        ->and($shared['auth'])->toHaveKey('user')
        ->and($shared['auth']['user'])->toBeNull();
});

it('shares authenticated user data', function (): void {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect($shared['auth']['user'])->not->toBeNull()
        ->and($shared['auth']['user']->id)->toBe($user->id)
        ->and($shared['auth']['user']->name)->toBe('Test User')
        ->and($shared['auth']['user']->email)->toBe('test@example.com');
});

it('defaults sidebarOpen to true when no cookie', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('sidebarOpen')
        ->and($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to true when cookie is true', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'true');

    $shared = $middleware->share($request);

    expect($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to false when cookie is false', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'false');

    $shared = $middleware->share($request);

    expect($shared['sidebarOpen'])->toBeFalse();
});

it('includes parent shared data', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    // Parent Inertia middleware shares 'errors' by default
    expect($shared)->toHaveKey('errors');
});

it('shares no years for a guest', function (): void {
    $middleware = resolve(HandleInertiaRequests::class);

    $shared = $middleware->share(Request::create('/', 'GET'));

    expect($shared['years'])->toBe([])
        ->and($shared['selectedYear'])->toBeNull();
});

it('shares the years the switcher lists, newest first', function (): void {
    $user = User::factory()->create();

    FinancialYear::factory()->for($user)->create(['year' => 2025, 'setup_completed_at' => null]);
    FinancialYear::factory()->for($user)->create(['year' => 2027, 'setup_completed_at' => now()]);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = resolve(HandleInertiaRequests::class)->share($request);

    expect($shared['years'])->toBe([
        ['year' => 2027, 'isSetupComplete' => true],
        ['year' => 2025, 'isSetupComplete' => false],
    ]);
});

it('takes the selected year from a bound route parameter', function (): void {
    $user = User::factory()->create();
    $year = FinancialYear::factory()->for($user)->create(['year' => 2025]);

    FinancialYear::factory()->for($user)->create(['year' => 2027]);

    $request = Request::create('/years/2025/plan/income', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route(['GET'], '/years/{year}/plan/{tab}', []);
    $route->bind($request);
    $route->setParameter('year', $year);

    $request->setRouteResolver(fn (): Route => $route);

    expect(resolve(HandleInertiaRequests::class)->share($request)['selectedYear'])->toBe(2025);
});

it('takes the selected year from an unbound route parameter', function (): void {
    $user = User::factory()->create();

    FinancialYear::factory()->for($user)->create(['year' => 2025]);
    FinancialYear::factory()->for($user)->create(['year' => 2027]);

    $request = Request::create('/years/2025/plan/income', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route(['GET'], '/years/{year}/plan/{tab}', []);
    $route->bind($request);
    $route->setParameter('year', '2025');

    $request->setRouteResolver(fn (): Route => $route);

    expect(resolve(HandleInertiaRequests::class)->share($request)['selectedYear'])->toBe(2025);
});

it('takes the selected year from the query string', function (): void {
    $user = User::factory()->create();

    FinancialYear::factory()->for($user)->create(['year' => 2025]);
    FinancialYear::factory()->for($user)->create(['year' => 2027]);

    $request = Request::create('/transactions?year=2025', 'GET');
    $request->setUserResolver(fn () => $user);

    expect(resolve(HandleInertiaRequests::class)->share($request)['selectedYear'])->toBe(2025);
});

it('ignores a route parameter that is not a year', function (): void {
    $user = User::factory()->create();

    FinancialYear::factory()->for($user)->create(['year' => 2027]);

    $request = Request::create('/years/nonsense/plan/income', 'GET');
    $request->setUserResolver(fn () => $user);

    $route = new Route(['GET'], '/years/{year}/plan/{tab}', []);
    $route->bind($request);
    $route->setParameter('year', 'nonsense');

    $request->setRouteResolver(fn (): Route => $route);

    expect(resolve(HandleInertiaRequests::class)->share($request)['selectedYear'])->toBe(2027);
});

it('ignores a query string year that is not a number', function (): void {
    $user = User::factory()->create();

    FinancialYear::factory()->for($user)->create(['year' => 2027]);

    $request = Request::create('/transactions?year=next', 'GET');
    $request->setUserResolver(fn () => $user);

    expect(resolve(HandleInertiaRequests::class)->share($request)['selectedYear'])->toBe(2027);
});
