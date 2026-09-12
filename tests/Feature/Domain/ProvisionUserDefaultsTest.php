<?php

declare(strict_types=1);

use App\Actions\ProvisionUserDefaults;
use App\Enums\AccountType;
use App\Enums\GoalType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserPreference;

function provisionedUser(): User
{
    $user = User::factory()->create();

    resolve(ProvisionUserDefaults::class)->handle($user);

    return $user;
}

it('creates preferences at their documented defaults', function (): void {
    $user = provisionedUser();

    $preference = UserPreference::query()->where('user_id', $user->id)->firstOrFail();

    expect($preference->currency)->toBe('EUR')
        ->and($preference->format_locale)->toBe('el-GR')
        ->and($preference->timezone)->toBe('Europe/Athens')
        ->and($preference->salary_payments)->toBe(14)
        ->and($preference->emergency_fund_months)->toBe(6)
        ->and($preference->budget_warning_threshold_percent)->toBe(10);
});

it('creates a cash account and points the preference at it', function (): void {
    $user = provisionedUser();

    $account = Account::query()->where('user_id', $user->id)->firstOrFail();
    $preference = UserPreference::query()->where('user_id', $user->id)->firstOrFail();

    expect($account->name)->toBe('Cash')
        ->and($account->type)->toBe(AccountType::Cash)
        ->and($account->is_active)->toBeTrue()
        ->and($preference->default_account_id)->toBe($account->id);
});

it('creates exactly the documented category tree', function (): void {
    $user = provisionedUser();

    $categories = Category::query()->where('user_id', $user->id)->get();

    expect($categories->whereNull('parent_id'))->toHaveCount(24)
        ->and($categories->whereNotNull('parent_id'))->toHaveCount(6)
        ->and($categories->where('type', TransactionType::Income)->whereNull('parent_id'))->toHaveCount(9)
        ->and($categories->where('type', TransactionType::Expense)->whereNull('parent_id'))->toHaveCount(15);
});

it('marks the essential expense categories', function (): void {
    $user = provisionedUser();

    $essential = Category::query()
        ->where('user_id', $user->id)
        ->where('is_essential', true)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($essential)->toBe([
        'Food & Groceries',
        'Housing',
        'Personal Care & Health',
        'Phone & Internet',
        'Transportation',
        'Utilities',
    ]);
});

it('marks the irregular expense categories', function (): void {
    $user = provisionedUser();

    $irregular = Category::query()
        ->where('user_id', $user->id)
        ->where('is_irregular', true)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($irregular)->toBe([
        'AADE (Taxes)',
        'Annual Insurance',
        'Car Expenses',
        'Holidays',
        'Large Purchases',
        'Other Irregular Expenses',
    ]);
});

it('assigns the system keys that later milestones reference', function (): void {
    $user = provisionedUser();

    $keyed = Category::query()
        ->where('user_id', $user->id)
        ->whereNotNull('system_key')
        ->pluck('name', 'system_key')
        ->sortKeys()
        ->all();

    expect($keyed)->toBe([
        Category::KEY_CHRISTMAS_BONUS => 'Christmas Bonus',
        Category::KEY_EASTER_BONUS => 'Easter Bonus',
        Category::KEY_SALARY => 'Salary',
        Category::KEY_SUBSCRIPTIONS => 'Subscriptions',
        Category::KEY_VACATION_ALLOWANCE => 'Vacation Allowance',
    ]);
});

it('nests each subcategory under the right parent, inheriting its type', function (string $child, string $parent): void {
    $user = provisionedUser();

    $category = Category::query()
        ->where('user_id', $user->id)
        ->where('name', $child)
        ->firstOrFail();

    expect($category->parent?->name)->toBe($parent)
        ->and($category->type)->toBe(TransactionType::Expense);
})->with([
    ['Supermarket', 'Food & Groceries'],
    ["Farmers' market (Laiki)", 'Food & Groceries'],
    ['Electricity', 'Utilities'],
    ['Water', 'Utilities'],
    ['Coffee', 'Dining Out'],
    ['Restaurant', 'Dining Out'],
]);

it('creates exactly one emergency fund goal with a computed target', function (): void {
    $user = provisionedUser();

    $goals = Goal::query()->where('user_id', $user->id)->get();

    expect($goals)->toHaveCount(1)
        ->and($goals->first()?->type)->toBe(GoalType::EmergencyFund)
        ->and($goals->first()?->target_amount_cents)->toBeNull()
        ->and($goals->first()?->target_is_custom)->toBeFalse();
});

it('is safe to run twice', function (): void {
    $user = provisionedUser();

    resolve(ProvisionUserDefaults::class)->handle($user);

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(30)
        ->and(Account::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Goal::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(UserPreference::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('provisions a user who accepts an invitation', function (): void {
    Invitation::factory()->withToken('a-token')->create(['email' => 'invitee@example.test']);

    $this->post(route('invitation-acceptance.store', ['token' => 'a-token']), [
        'name' => 'Invited Person',
        'password' => 'StrongPassword1234',
        'password_confirmation' => 'StrongPassword1234',
    ])->assertRedirectToRoute('dashboard');

    $user = User::query()->where('email', 'invitee@example.test')->firstOrFail();

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(30)
        ->and(Account::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Goal::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(UserPreference::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
