<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | How long an invitation link stays usable after it is issued (INV-03).
    |
    */

    'expires_after_days' => (int) env('INVITATIONS_EXPIRES_AFTER_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long expired, revoked and accepted invitations are kept before the
    | scheduled prune removes them (INV-11).
    |
    */

    'prune_after_days' => (int) env('INVITATIONS_PRUNE_AFTER_DAYS', 30),

];
