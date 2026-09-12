<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPreference>
 */
final class UserPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency' => 'EUR',
            'format_locale' => 'el-GR',
            'timezone' => 'Europe/Athens',
            'salary_payments' => 14,
            'default_account_id' => null,
            'emergency_fund_months' => 6,
            'budget_warning_threshold_percent' => 10,
        ];
    }
}
