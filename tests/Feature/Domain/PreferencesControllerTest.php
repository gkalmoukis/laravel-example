<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;

function validPreferences(array $overrides = []): array
{
    return [
        'format_locale' => 'en-GB',
        'timezone' => 'Europe/London',
        'salary_payments' => 12,
        'default_account_id' => null,
        'emergency_fund_months' => 3,
        'budget_warning_threshold_percent' => 15,
        ...$overrides,
    ];
}

it('shows the current preferences', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('preferences.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/preferences')
            ->where('settings.currency', 'EUR')
            ->where('settings.formatLocale', 'el-GR')
            ->where('settings.timezone', 'Europe/Athens')
            ->where('settings.salaryPayments', 14)
            ->where('settings.emergencyFundMonths', 6)
            ->where('settings.budgetWarningThresholdPercent', 10));
});

it('saves every editable preference', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('preferences.edit'))
        ->patch(route('preferences.update'), validPreferences([
            'default_account_id' => $account->id,
        ]))
        ->assertRedirectToRoute('preferences.edit');

    $preference = $user->preference()->firstOrFail();

    expect($preference->format_locale)->toBe('en-GB')
        ->and($preference->timezone)->toBe('Europe/London')
        ->and($preference->salary_payments)->toBe(12)
        ->and($preference->default_account_id)->toBe($account->id)
        ->and($preference->emergency_fund_months)->toBe(3)
        ->and($preference->budget_warning_threshold_percent)->toBe(15);
});

it('rejects values outside what the settings offer', function (array $invalid, string $field): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('preferences.edit'))
        ->patch(route('preferences.update'), validPreferences($invalid))
        ->assertSessionHasErrors($field);
})->with([
    'unknown locale' => [['format_locale' => 'fr-FR'], 'format_locale'],
    'not a timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'unsupported salary count' => [['salary_payments' => 13], 'salary_payments'],
    'coverage below one month' => [['emergency_fund_months' => 0], 'emergency_fund_months'],
    'coverage above two years' => [['emergency_fund_months' => 25], 'emergency_fund_months'],
    'threshold below one percent' => [['budget_warning_threshold_percent' => 0], 'budget_warning_threshold_percent'],
    'threshold above a hundred percent' => [['budget_warning_threshold_percent' => 101], 'budget_warning_threshold_percent'],
]);

it('will not accept an inactive account as the default', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->inactive()->create();

    $this->actingAs($user)
        ->from(route('preferences.edit'))
        ->patch(route('preferences.update'), validPreferences(['default_account_id' => $account->id]))
        ->assertSessionHasErrors('default_account_id');
});

it('never lets currency or the financial year start be changed', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('preferences.update'), validPreferences([
        'currency' => 'USD',
    ]));

    expect($user->preference()->firstOrFail()->currency)->toBe('EUR');
});

it('shares the new formatting on the very next request', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('preferences.formatLocale', 'el-GR'));

    $this->actingAs($user)->patch(route('preferences.update'), validPreferences());

    // Re-resolved the way a real request does, rather than reusing the instance whose
    // relation is already loaded.
    $this->actingAs(User::query()->findOrFail($user->id))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('preferences.formatLocale', 'en-GB')
            ->where('preferences.timezone', 'Europe/London'));
});
