<?php

declare(strict_types=1);

use App\Actions\BuildQuickAddOptions;
use App\Enums\EntrySource;
use App\Enums\TransactionType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\OptionalProp;

function quickAddCategory(User $user, string $name): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function quickAddOptions(User $user): array
{
    return resolve(BuildQuickAddOptions::class)->handle($user);
}

function spend(User $user, Category $category, string $date, int $times = 1): void
{
    Transaction::factory()->count($times)->for($user)->create([
        'type' => $category->type,
        'category_id' => $category->id,
        'occurred_on' => $date,
    ]);
}

it('is not sent until the sheet asks for it', function (): void {
    [$user] = userWithYear();

    // A plain page load must not pay for the category and account queries (TXQ-01).
    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page->missing('quickAdd'));
});

it('is offered as a prop the sheet can ask for', function (): void {
    [$user] = userWithYear();

    $request = Request::create('/transactions');
    $request->setUserResolver(fn (): User => $user);

    $shared = resolve(HandleInertiaRequests::class)->share($request);

    expect($shared['quickAdd'])->toBeInstanceOf(OptionalProp::class);

    // Resolving it is what a partial reload does; the closure is the part this
    // application owns, so it is the part worth asserting.
    $resolved = ($shared['quickAdd'])();

    expect($resolved)->toBeArray()
        ->and(collect($resolved['categories'])->pluck('name'))->toContain('Housing');
});

it('offers a guest nothing to add', function (): void {
    $shared = resolve(HandleInertiaRequests::class)->share(Request::create('/'));

    expect(($shared['quickAdd'])())->toBeNull();
});

it('offers only active categories of the user', function (): void {
    [$user] = userWithYear();
    [$other] = userWithYear();

    quickAddCategory($user, 'Holidays')->update(['is_active' => false]);

    $names = collect(quickAddOptions($user)['categories'])->pluck('name');

    expect($names)->toContain('Housing')
        ->and($names)->not->toContain('Holidays')
        ->and($names)->not->toBeEmpty();

    expect(collect(quickAddOptions($other)['categories'])->pluck('name'))->toContain('Housing');
});

it('nests subcategories so typing one can fill both fields', function (): void {
    [$user] = userWithYear();

    $food = collect(quickAddOptions($user)['categories'])->firstWhere('name', 'Food & Groceries');

    expect(collect($food['subcategories'])->pluck('name'))->toContain('Supermarket');
});

it('puts the five most used categories first, per direction', function (): void {
    [$user] = userWithYear();

    $housing = quickAddCategory($user, 'Housing');
    $food = quickAddCategory($user, 'Food & Groceries');
    $salary = quickAddCategory($user, 'Salary');

    spend($user, $food, '2027-03-01', 5);
    spend($user, $housing, '2027-03-01', 2);
    spend($user, $salary, '2027-03-01', 3);

    $this->travelTo('2027-03-15');

    $mostUsed = quickAddOptions($user)['mostUsedCategoryIds'];

    expect($mostUsed[TransactionType::Expense->value])->toBe([$food->id, $housing->id])
        ->and($mostUsed[TransactionType::Income->value])->toBe([$salary->id]);
});

it('counts at most five', function (): void {
    [$user] = userWithYear();

    foreach (['Housing', 'Food & Groceries', 'Utilities', 'Transportation', 'Dining Out', 'Miscellaneous'] as $name) {
        spend($user, quickAddCategory($user, $name), '2027-03-01');
    }

    $this->travelTo('2027-03-15');

    expect(quickAddOptions($user)['mostUsedCategoryIds'][TransactionType::Expense->value])->toHaveCount(5);
});

it('forgets a habit older than ninety days', function (): void {
    [$user] = userWithYear();

    spend($user, quickAddCategory($user, 'Housing'), '2027-01-01');

    $this->travelTo('2027-06-01');

    expect(quickAddOptions($user)['mostUsedCategoryIds'][TransactionType::Expense->value])->toBe([]);
});

it('preselects the account the user reached for last', function (): void {
    [$user] = userWithYear();

    $old = Account::factory()->for($user)->create(['name' => 'Old', 'is_active' => true]);
    $recent = Account::factory()->for($user)->create(['name' => 'Recent', 'is_active' => true]);

    Transaction::factory()->for($user)->create([
        'category_id' => quickAddCategory($user, 'Housing')->id,
        'account_id' => $old->id,
        'occurred_on' => '2027-03-01',
    ]);

    $this->travelTo(now()->addMinute());

    Transaction::factory()->for($user)->create([
        'category_id' => quickAddCategory($user, 'Housing')->id,
        'account_id' => $recent->id,
        'occurred_on' => '2027-02-01',
    ]);

    // Most recently *entered*, not most recently dated: the last one typed is the one the
    // next entry most likely shares (ACC-02).
    expect(quickAddOptions($user)['defaultAccountId'])->toBe($recent->id);
});

it('falls back to the stated default account', function (): void {
    [$user] = userWithYear();

    $account = Account::factory()->for($user)->create(['is_active' => true]);

    $user->preference->update(['default_account_id' => $account->id]);

    expect(quickAddOptions($user->fresh() ?? $user)['defaultAccountId'])->toBe($account->id);
});

it('preselects nothing rather than guessing', function (): void {
    // Provisioning gives every invited account a Cash account and points the preference
    // at it, so "nothing to preselect" only arises before that has happened.
    $user = User::factory()->create();

    expect(quickAddOptions($user)['defaultAccountId'])->toBeNull();
});

it('preselects the Cash account a new user is given', function (): void {
    [$user] = userWithYear();

    $cash = $user->accounts()->where('name', 'Cash')->firstOrFail();

    expect(quickAddOptions($user)['defaultAccountId'])->toBe($cash->id);
});

it('offers only active accounts', function (): void {
    [$user] = userWithYear();

    Account::factory()->for($user)->create(['name' => 'Old card', 'is_active' => false]);

    expect(collect(quickAddOptions($user)['accounts'])->pluck('name'))
        ->not->toContain('Old card');
});

it('records what quick add sends', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'amount' => '12,50',
        'occurred_on' => '2027-03-03',
        'category_id' => quickAddCategory($user, 'Housing')->id,
        'description' => 'Rent',
        'entry_source' => EntrySource::QuickAdd->value,
        'entry_duration_ms' => 9_200,
    ])->assertSessionHasNoErrors();

    $transaction = $user->transactions()->sole();

    expect($transaction->entry_source)->toBe(EntrySource::QuickAdd)
        ->and($transaction->entry_duration_ms)->toBe(9_200);
});
