<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\DeleteTransaction;
use App\Actions\UpdateTransaction;
use App\Enums\TransactionIssue;
use App\Enums\TransactionType;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TransactionController
{
    /**
     * Enough to scan a month at a time without scrolling forever (TXL-01).
     */
    private const int PER_PAGE = 50;

    public function index(IndexTransactionRequest $request, #[CurrentUser] User $user): Response
    {
        $page = Transaction::withIssues($this->filtered($request, $user))
            ->with(['category', 'subcategory', 'account'])
            ->latest('occurred_on')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('transactions/index', [
            'transactions' => $page->through($this->present(...))->items(),
            'pagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'perPage' => self::PER_PAGE,
            ],
            'totals' => $this->totals($request, $user),
            'filters' => $request->filters(),
            'options' => $this->options($user),
        ]);
    }

    public function store(StoreTransactionRequest $request, #[CurrentUser] User $user, CreateTransaction $action): RedirectResponse
    {
        $action->handle($user, $request->transactionAttributes(), $request->amount(), $request->occurredOn(), $request->reopensMonth());

        return back()->with('status', 'Transaction saved.');
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $action): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $action->handle($transaction, $request->transactionAttributes(), $request->amount(), $request->occurredOn(), $request->reopensMonth());

        return back()->with('status', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction, DeleteTransaction $action): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $action->handle($transaction, request()->boolean('reopen_month'));

        return back()->with('status', 'Transaction deleted.');
    }

    /**
     * Income, expenses and net for everything the filter matches — not just the page on
     * screen, which would make the footer change as the user pages through (TXL-05).
     *
     * Flagged rows are included: this summarises the list the user is looking at, rather
     * than reporting a figure, and a total that quietly disagreed with the visible rows
     * would be harder to trust than one that matches.
     *
     * @return array<string, int>
     */
    private function totals(IndexTransactionRequest $request, User $user): array
    {
        $income = (int) $this->filtered($request, $user)
            ->where('type', TransactionType::Income)
            ->sum('amount_cents');

        $expenses = (int) $this->filtered($request, $user)
            ->where('type', TransactionType::Expense)
            ->sum('amount_cents');

        // Net is a derived figure and may legitimately be negative, so it stays a signed
        // integer rather than becoming Money.
        return [
            'incomeCents' => $income,
            'expenseCents' => $expenses,
            'netCents' => $income - $expenses,
        ];
    }

    /**
     * @return Builder<Transaction>
     */
    private function filtered(IndexTransactionRequest $request, User $user): Builder
    {
        // Scoped to the owner before any filter is applied, so no filter can ever widen
        // what is visible (USR-02).
        $query = Transaction::query()->where('transactions.user_id', $user->id);

        $from = $request->from();
        $to = $request->to();
        $month = $request->month();
        $type = $request->type();
        $categoryId = $request->categoryId();
        $subcategoryId = $request->subcategoryId();
        $accountId = $request->accountId();
        $minAmount = $request->minAmount();
        $maxAmount = $request->maxAmount();
        $search = $request->search();

        if ($from instanceof CarbonImmutable) {
            $query->whereDate('occurred_on', '>=', $from);
        }

        if ($to instanceof CarbonImmutable) {
            $query->whereDate('occurred_on', '<=', $to);
        }

        if ($month !== null) {
            $query->whereYear('occurred_on', $request->year())->whereMonth('occurred_on', $month);
        }

        if ($type instanceof TransactionType) {
            $query->where('type', $type);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($subcategoryId !== null) {
            $query->where('subcategory_id', $subcategoryId);
        }

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        if ($minAmount instanceof Money) {
            $query->where('amount_cents', '>=', $minAmount->cents);
        }

        if ($maxAmount instanceof Money) {
            $query->where('amount_cents', '<=', $maxAmount->cents);
        }

        if ($search !== null) {
            $query->whereLike('description', '%'.$search.'%');
        }

        if ($request->onlyIssues()) {
            Transaction::flagged($query);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'occurredOn' => $transaction->occurred_on->toDateString(),
            'amountCents' => $transaction->amount_cents->cents,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'categoryId' => $transaction->category_id,
            'categoryName' => $transaction->category->name,
            'subcategoryId' => $transaction->subcategory_id,
            'subcategoryName' => $transaction->subcategory?->name,
            'accountId' => $transaction->account_id,
            'accountName' => $transaction->account?->name,
            'issues' => array_map(
                fn (TransactionIssue $issue): array => [
                    'key' => $issue->value,
                    'reason' => $issue->reason(),
                ],
                $transaction->loadedIssues(),
            ),
        ];
    }

    /**
     * What the filter panel offers. Inactive categories and accounts still appear on the
     * transactions that already use them, but are not offered as new choices (ACC-01).
     *
     * @return array<string, mixed>
     */
    private function options(User $user): array
    {
        $categories = $user->categories()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories
                ->filter(fn (Category $category): bool => $category->isTopLevel())
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                    'subcategories' => $categories
                        ->filter(fn (Category $child): bool => $child->parent_id === $category->id)
                        ->map(fn (Category $child): array => ['id' => $child->id, 'name' => $child->name])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),

            'accounts' => $user->accounts()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name])
                ->all(),
        ];
    }
}
