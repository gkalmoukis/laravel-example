<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPreference;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'email_verified_at',
            'is_admin',
            'two_factor_confirmed_at',
            'created_at',
            'updated_at',
        ]);
});

it('always has preferences to read', function (): void {
    $provisioned = planningUser();

    expect($provisioned->preferences()->emergency_fund_months)
        ->toBe(UserPreference::DEFAULT_EMERGENCY_FUND_MONTHS);

    // An account that exists before provisioning has finished still answers, with the
    // documented defaults rather than a null dereference.
    expect(User::factory()->create()->preferences()->emergency_fund_months)
        ->toBe(UserPreference::DEFAULT_EMERGENCY_FUND_MONTHS);
});
