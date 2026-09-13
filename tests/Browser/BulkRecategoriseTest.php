<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;

/*
 * Browser tests assert through the interface. The application runs in a separate process,
 * so a model re-read here would return a stale snapshot.
 */

function miscExpense(User $user, string $description): Transaction
{
    return Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $user->categories()->where('name', 'Miscellaneous')->whereNull('parent_id')->firstOrFail()->id,
        'occurred_on' => date('Y-m-d'),
        'description' => $description,
    ]);
}

it('refiles the rows the user picks', function (): void {
    [$user] = userWithYear((int) date('Y'));

    $one = miscExpense($user, 'Rent March');
    miscExpense($user, 'Leave me alone');

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@select-'.$one->id)
        ->assertSee('1 selected')
        ->click('@bulk-category')
        ->click('[role="option"]:has-text("Housing")')
        ->click('@apply-bulk-category')
        ->assertSee('Housing')
        ->assertNoJavascriptErrors();
});

it('selects every row on the page at once', function (): void {
    [$user] = userWithYear((int) date('Y'));

    miscExpense($user, 'One');
    miscExpense($user, 'Two');
    miscExpense($user, 'Three');

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@select-all')
        ->assertSee('3 selected')
        ->assertNoJavascriptErrors();
});

it('lets go of the selection', function (): void {
    [$user] = userWithYear((int) date('Y'));

    miscExpense($user, 'One');

    $this->actingAs($user)
        ->visit('/transactions')
        ->click('@select-all')
        ->assertSee('1 selected')
        ->click('@clear-selection')
        ->assertDontSee('1 selected')
        ->assertNoJavascriptErrors();
});
