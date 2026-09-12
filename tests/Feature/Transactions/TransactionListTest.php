<?php

declare(strict_types=1);

use App\Enums\TransactionIssue;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

function listCategory(User $user, string $name = 'Housing'): Category
{
    return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
}

function recordExpense(User $user, string $date, int $cents, string $description = 'Rent'): Transaction
{
    return Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => listCategory($user)->id,
        'occurred_on' => $date,
        'amount_cents' => $cents,
        'description' => $description,
    ]);
}

it('lists a user transactions newest first', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000, 'Older');
    recordExpense($user, '2027-03-20', 2_000, 'Newer');

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('transactions/index')
            ->where('transactions.0.description', 'Newer')
            ->where('transactions.1.description', 'Older'));
});

it('shows only this user transactions', function (): void {
    [$user] = userWithYear();
    [$other] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000, 'Mine');
    recordExpense($other, '2027-03-01', 1_000, 'Theirs');

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.description', 'Mine'));
});

it('pages fifty at a time', function (): void {
    [$user] = userWithYear();

    Transaction::factory()->count(55)->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => listCategory($user)->id,
        'occurred_on' => '2027-03-01',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 50)
            ->where('pagination.lastPage', 2)
            ->where('pagination.total', 55));

    $this->actingAs($user)
        ->get(route('transactions.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('transactions', 5));
});

it('totals the whole filter rather than the page on screen', function (): void {
    [$user] = userWithYear();

    Transaction::factory()->count(55)->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => listCategory($user)->id,
        'occurred_on' => '2027-03-01',
        'amount_cents' => 1_000,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page->where('totals.expenseCents', 55_000));
});

it('reports income, expenses and a net that may go negative', function (): void {
    [$user] = userWithYear();

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'category_id' => $salary->id,
        'occurred_on' => '2027-03-01',
        'amount_cents' => 100_000,
    ]);

    recordExpense($user, '2027-03-02', 150_000);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('totals.incomeCents', 100_000)
            ->where('totals.expenseCents', 150_000)
            ->where('totals.netCents', -50_000));
});

it('filters by a date range', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-01-15', 1_000, 'January');
    recordExpense($user, '2027-06-15', 1_000, 'June');

    $this->actingAs($user)
        ->get(route('transactions.index', ['from' => '2027-06-01', 'to' => '2027-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.description', 'June'));
});

it('filters by a month of the selected year', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-15', 1_000, 'March');
    recordExpense($user, '2027-04-15', 1_000, 'April');

    $this->actingAs($user)
        ->get(route('transactions.index', ['month' => 3, 'year' => 2027]))
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.description', 'March'));
});

it('filters by type', function (): void {
    [$user] = userWithYear();

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'category_id' => $salary->id,
        'occurred_on' => '2027-03-01',
        'description' => 'Pay',
    ]);

    recordExpense($user, '2027-03-02', 1_000, 'Rent');

    $this->actingAs($user)
        ->get(route('transactions.index', ['type' => TransactionType::Income->value]))
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.description', 'Pay'));
});

it('filters by category and subcategory', function (): void {
    [$user] = userWithYear();

    $food = listCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
        'occurred_on' => '2027-03-01',
        'description' => 'Shopping',
    ]);

    recordExpense($user, '2027-03-02', 1_000, 'Rent');

    $this->actingAs($user)
        ->get(route('transactions.index', ['category_id' => $food->id]))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.description', 'Shopping'));

    $this->actingAs($user)
        ->get(route('transactions.index', ['subcategory_id' => $supermarket->id]))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.subcategoryName', 'Supermarket'));
});

it('filters by account', function (): void {
    [$user] = userWithYear();

    $account = Account::factory()->for($user)->create(['name' => 'Everyday', 'is_active' => true]);

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => listCategory($user)->id,
        'account_id' => $account->id,
        'occurred_on' => '2027-03-01',
        'description' => 'Card',
    ]);

    recordExpense($user, '2027-03-02', 1_000, 'Cash');

    $this->actingAs($user)
        ->get(route('transactions.index', ['account_id' => $account->id]))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.accountName', 'Everyday'));
});

it('filters by an amount range', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 500, 'Small');
    recordExpense($user, '2027-03-02', 50_000, 'Large');

    $this->actingAs($user)
        ->get(route('transactions.index', ['min_amount' => '100,00']))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.description', 'Large'));

    $this->actingAs($user)
        ->get(route('transactions.index', ['max_amount' => '100,00']))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.description', 'Small'));
});

it('searches descriptions without minding case', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000, 'Weekly SHOP');
    recordExpense($user, '2027-03-02', 1_000, 'Rent');

    $this->actingAs($user)
        ->get(route('transactions.index', ['q' => 'shop']))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.description', 'Weekly SHOP'));
});

it('shows only flagged transactions when asked', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000, 'Counted');
    // 2029 has no plan, so this one cannot be counted yet.
    recordExpense($user, '2029-03-01', 1_000, 'Orphan');

    $this->actingAs($user)
        ->get(route('transactions.index', ['issues' => '1']))
        ->assertInertia(fn ($page) => $page->has('transactions', 1)
            ->where('transactions.0.description', 'Orphan'));
});

it('explains why a transaction is not being counted', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2029-03-01', 1_000, 'Orphan');

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.0.issues.0.key', TransactionIssue::NoFinancialYear->value)
            ->where('transactions.0.issues.0.reason', TransactionIssue::NoFinancialYear->reason()));
});

it('carries no issues on a transaction that counts', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page->has('transactions.0.issues', 0));
});

it('ignores filter values that make no sense', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000, 'Rent');

    $this->actingAs($user)
        ->get(route('transactions.index', [
            'from' => 'whenever',
            'month' => '99',
            'type' => 'Sideways',
            'category_id' => 'none',
            'subcategory_id' => '0',
            'account_id' => '-3',
            'min_amount' => 'lots',
            'max_amount' => '',
            'q' => '   ',
            'year' => 'soon',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transactions', 1));
});

it('keeps the filter state in the props so the form and the address agree', function (): void {
    [$user] = userWithYear();

    recordExpense($user, '2027-03-01', 1_000);

    $this->actingAs($user)
        ->get(route('transactions.index', ['q' => 'Rent', 'type' => TransactionType::Expense->value, 'issues' => '1']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', 'Rent')
            ->where('filters.type', TransactionType::Expense->value)
            ->where('filters.onlyIssues', true));
});

it('offers only active categories and accounts as filters', function (): void {
    [$user] = userWithYear();

    $retired = Account::factory()->for($user)->create(['name' => 'Old card', 'is_active' => false]);
    Account::factory()->for($user)->create(['name' => 'Everyday', 'is_active' => true]);

    listCategory($user, 'Holidays')->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(function ($page) use ($retired): void {
            $accounts = collect($page->toArray()['props']['options']['accounts']);
            $categories = collect($page->toArray()['props']['options']['categories']);

            expect($accounts->pluck('id'))->not->toContain($retired->id)
                ->and($accounts->pluck('name'))->toContain('Everyday')
                ->and($categories->pluck('name'))->not->toContain('Holidays');
        });
});

it('nests subcategories under their category in the filter options', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(function ($page): void {
            $food = collect($page->toArray()['props']['options']['categories'])
                ->firstWhere('name', 'Food & Groceries');

            expect(collect($food['subcategories'])->pluck('name'))->toContain('Supermarket');
        });
});

it('shows an empty list when nothing has been recorded', function (): void {
    [$user] = userWithYear();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 0)
            ->where('totals.netCents', 0));
});

it('explains a category recording the opposite direction', function (): void {
    [$user] = userWithYear();

    $salary = $user->categories()->where('name', 'Salary')->firstOrFail();

    // Validation refuses this combination, so it can only arrive by the category being
    // changed underneath an existing transaction. The list still has to explain it.
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $salary->id,
        'occurred_on' => '2027-03-01',
        'description' => 'Misfiled',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.0.issues.0.key', TransactionIssue::CategoryTypeMismatch->value)
            ->where('transactions.0.issues.0.reason', TransactionIssue::CategoryTypeMismatch->reason()));
});

it('explains a subcategory that has been moved away', function (): void {
    [$user] = userWithYear();

    $food = listCategory($user, 'Food & Groceries');
    $supermarket = $user->categories()->where('name', 'Supermarket')->firstOrFail();

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food->id,
        'subcategory_id' => $supermarket->id,
        'occurred_on' => '2027-03-01',
        'description' => 'Weekly shop',
    ]);

    // The subcategory moves house; the transaction stays where it was filed.
    $supermarket->update(['parent_id' => listCategory($user, 'Dining Out')->id]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.0.issues.0.key', TransactionIssue::SubcategoryParentMismatch->value)
            ->where('transactions.0.issues.0.reason', TransactionIssue::SubcategoryParentMismatch->reason()));
});
