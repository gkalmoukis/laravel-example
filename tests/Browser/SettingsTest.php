<?php

declare(strict_types=1);

use App\Actions\ProvisionUserDefaults;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;

function settingsUser(): User
{
    $user = User::factory()->admin()->withoutTwoFactor()->create();

    resolve(ProvisionUserDefaults::class)->handle($user);

    return $user;
}

it('manages categories end to end', function (): void {
    $user = settingsUser();

    $page = $this->actingAs($user)->visit('/settings/categories');

    $page->assertSee('Housing')
        ->assertSee('Food & Groceries')
        ->fill('name', 'Hobbies')
        ->click('@add-category-button')
        ->assertSee('Hobbies')
        ->assertNoJavascriptErrors();

    // Built-in categories are renameable but offer no way to remove them.
    $page->assertSee('Built-in')->assertNoJavascriptErrors();
});

it('adds a subcategory under a category', function (): void {
    $user = settingsUser();
    $parent = $user->categories()->where('name', 'Housing')->firstOrFail();

    $this->actingAs($user)
        ->visit('/settings/categories')
        ->fill('#sub-'.$parent->id, 'Rent')
        ->click('Add')
        ->assertSee('Rent')
        ->assertNoJavascriptErrors();
});

it('deactivates a category behind a confirmation', function (): void {
    $user = settingsUser();
    Category::factory()->for($user)->expense()->create(['name' => 'Temporary']);

    $this->actingAs($user)
        ->visit('/settings/categories')
        ->assertSee('Temporary')
        ->click('Deactivate')
        ->assertSee('Keep it active')
        ->click('@confirm-deactivate-category-button')
        ->assertSee('Inactive')
        ->assertNoJavascriptErrors();
});

it('switches between expense and income categories', function (): void {
    $user = settingsUser();

    $this->actingAs($user)
        ->visit('/settings/categories')
        ->assertSee('Housing')
        ->click('Income')
        ->assertSee('Salary')
        ->assertDontSee('Housing')
        ->assertNoJavascriptErrors();
});

it('manages accounts end to end', function (): void {
    $user = settingsUser();

    $this->actingAs($user)
        ->visit('/settings/accounts')
        ->assertSee('Cash')
        ->fill('name', 'Savings')
        ->click('@add-account-button')
        ->assertSee('Savings')
        ->click('Deactivate')
        ->assertSee('Keep it active')
        ->click('@confirm-deactivate-account-button')
        ->assertSee('Inactive')
        ->assertNoJavascriptErrors();
});

it('previews the chosen number and date format before saving', function (): void {
    $user = settingsUser();

    $page = $this->actingAs($user)->visit('/settings/preferences');

    // The Greek default: dot thousands, comma decimals, sign after the amount.
    $page->assertSee('1.234,56')
        ->assertSee('31/12/2027')
        ->assertSee('More options coming later')
        ->assertNoJavascriptErrors();
});

it('saves a preference change and keeps it', function (): void {
    $user = settingsUser();

    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->click('@save-preferences-button')
        ->assertSee('Saved')
        ->assertNoJavascriptErrors();

    expect($user->preference()->firstOrFail()->format_locale)->toBe('el-GR');
});

it('renders the settings screens on a phone without errors', function (string $path): void {
    $user = settingsUser();

    $this->actingAs($user)
        ->visit($path)
        ->on()->mobile()
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->with([
    '/settings/categories',
    '/settings/accounts',
    '/settings/preferences',
]);

it('shows a newly created account in the preferences picker', function (): void {
    $user = settingsUser();
    Account::factory()->for($user)->create(['name' => 'Joint account']);

    $this->actingAs($user)
        ->visit('/settings/preferences')
        ->assertSee('Default account')
        ->assertNoJavascriptErrors();
});
