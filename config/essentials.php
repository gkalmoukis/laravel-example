<?php

declare(strict_types=1);

use NunoMaduro\Essentials\Configurables\AggressivePrefetching;
use NunoMaduro\Essentials\Configurables\AutomaticallyEagerLoadRelationships;
use NunoMaduro\Essentials\Configurables\FakeSleep;
use NunoMaduro\Essentials\Configurables\ForceScheme;
use NunoMaduro\Essentials\Configurables\ImmutableDates;
use NunoMaduro\Essentials\Configurables\PreventStrayRequests;
use NunoMaduro\Essentials\Configurables\ProhibitDestructiveCommands;
use NunoMaduro\Essentials\Configurables\SetDefaultPassword;
use NunoMaduro\Essentials\Configurables\ShouldBeStrict;
use NunoMaduro\Essentials\Configurables\Unguard;

return [

    // Prefetch built assets so navigations feel instant.
    AggressivePrefetching::class => true,

    // Eager load relationships automatically; keep $with minimal.
    AutomaticallyEagerLoadRelationships::class => true,

    // Sleep() is faked in tests so timing never slows the suite.
    FakeSleep::class => true,

    // HTTPS is forced outside local, so http://localhost keeps working under Sail.
    ForceScheme::class => true,

    // CarbonImmutable everywhere.
    ImmutableDates::class => true,

    // Unfaked outgoing HTTP in tests is an error.
    PreventStrayRequests::class => true,

    // Safe console: destructive commands are blocked in production.
    ProhibitDestructiveCommands::class => true,

    // Disabled on purpose. Essentials applies no password rules outside production and
    // adds symbols() in production, neither of which matches AUTH-04. Password::defaults()
    // is defined explicitly in FortifyServiceProvider instead.
    SetDefaultPassword::class => false,

    // Missing attributes, lazy loading and undefined attribute assignment all throw.
    ShouldBeStrict::class => true,

    // Mass-assignment protection stays on (D-08, SEC-07). The starter kit ships this as
    // true; this project deliberately overrides it.
    Unguard::class => false,

    'environments' => [
        ForceScheme::class => ['production'],
    ],

];
