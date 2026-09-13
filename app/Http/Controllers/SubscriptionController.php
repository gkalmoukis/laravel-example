<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSubscription;
use App\Actions\UpdateSubscription;
use App\Enums\Frequency;
use App\Enums\TransactionType;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the user is charged for on a schedule (SUB-01).
 */
final readonly class SubscriptionController
{
    public function index(#[CurrentUser] User $user): Response
    {
        $today = $user->today();

        $subscriptions = $user->subscriptions()
            ->with(['category', 'subcategory', 'account'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $rows = [];
        $monthlyTotal = 0;
        $annualTotal = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->is_active) {
                $monthlyTotal += $subscription->monthlyEquivalentCents();
                $annualTotal += $subscription->annualCents();
            }

            $rows[] = [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'amountCents' => $subscription->amount_cents->cents,
                'frequency' => $subscription->frequency->value,
                'monthlyEquivalentCents' => $subscription->monthlyEquivalentCents(),
                'annualCents' => $subscription->annualCents(),
                'nextBillingDate' => $this->nextBillingDate($subscription, $today),
                'billingAnchorDate' => $subscription->billing_anchor_date->toDateString(),
                'categoryId' => $subscription->category_id,
                'categoryName' => $subscription->category->name,
                'subcategoryName' => $subscription->subcategory?->name,
                'accountId' => $subscription->account_id,
                'accountName' => $subscription->account?->name,
                'isActive' => $subscription->is_active,
                'deactivatedOn' => $subscription->deactivated_on?->toDateString(),
                'notes' => $subscription->notes,
            ];
        }

        return Inertia::render('subscriptions/index', [
            'subscriptions' => $rows,
            'monthlyTotalCents' => $monthlyTotal,
            'annualTotalCents' => $annualTotal,
            'categories' => $this->categories($user),
            'accounts' => $this->accounts($user),
            'frequencies' => $this->frequencies(),
        ]);
    }

    public function store(StoreSubscriptionRequest $request, #[CurrentUser] User $user, CreateSubscription $action): RedirectResponse
    {
        $action->handle($user, $request->subscriptionAttributes(), $user->today());

        return back()->with('status', 'Subscription added.');
    }

    public function update(
        UpdateSubscriptionRequest $request,
        Subscription $subscription,
        #[CurrentUser] User $user,
        UpdateSubscription $action,
    ): RedirectResponse {
        Gate::authorize('update', $subscription);

        $action->handle($subscription, $request->subscriptionAttributes(), $user->today());

        return back()->with('status', 'Subscription updated.');
    }

    /**
     * Derived on every request, so it can never show a day already gone (SUB-03). A
     * stopped subscription has no next charge at all.
     */
    private function nextBillingDate(Subscription $subscription, CarbonImmutable $today): ?string
    {
        if (! $subscription->is_active) {
            return null;
        }

        return $subscription->nextBillingDateFrom($today)->toDateString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(User $user): array
    {
        $categories = $user->categories()
            ->where('type', TransactionType::Expense)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($categories as $category) {
            $rows[] = [
                'id' => $category->id,
                'name' => $category->name,
                // The screen defaults to this one, which is what most subscriptions are.
                'isDefault' => $category->system_key === Category::KEY_SUBSCRIPTIONS,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accounts(User $user): array
    {
        $accounts = $user->accounts()->where('is_active', true)->orderBy('name')->get();

        $rows = [];

        foreach ($accounts as $account) {
            $rows[] = ['id' => $account->id, 'name' => $account->name];
        }

        return $rows;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function frequencies(): array
    {
        return [
            ['value' => Frequency::Monthly->value, 'label' => 'Every month'],
            ['value' => Frequency::Quarterly->value, 'label' => 'Every three months'],
            ['value' => Frequency::SemiAnnual->value, 'label' => 'Every six months'],
            ['value' => Frequency::Annual->value, 'label' => 'Every year'],
        ];
    }
}
