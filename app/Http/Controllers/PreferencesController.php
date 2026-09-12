<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdatePreferences;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\Account;
use App\Models\User;
use App\Models\UserPreference;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PreferencesController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $preference = $user->preference()->firstOrCreate([]);

        return Inertia::render('settings/preferences', [
            // Named 'settings' because 'preferences' is already shared globally with the
            // formatting subset, and Inertia would merge the two.
            'settings' => [
                'currency' => $preference->currency,
                'formatLocale' => $preference->format_locale,
                'timezone' => $preference->timezone,
                'salaryPayments' => $preference->salary_payments,
                'defaultAccountId' => $preference->default_account_id,
                'emergencyFundMonths' => $preference->emergency_fund_months,
                'budgetWarningThresholdPercent' => $preference->budget_warning_threshold_percent,
            ],
            'formatLocales' => UserPreference::FORMAT_LOCALES,
            'timezones' => DateTimeZone::listIdentifiers(),
            'accounts' => $user->accounts()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                ])
                ->all(),
        ]);
    }

    public function update(UpdatePreferencesRequest $request, #[CurrentUser] User $user, UpdatePreferences $action): RedirectResponse
    {
        $action->handle($user, $request->validated());

        return to_route('preferences.edit')->with('status', 'Preferences saved.');
    }
}
