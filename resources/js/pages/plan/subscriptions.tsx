import { router } from '@inertiajs/react';
import { Plus, RefreshCw, Repeat } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/finance/empty-state';
import SubscriptionForm, {
    type AccountOption,
    type CategoryOption,
    type FrequencyOption,
    type SubscriptionValues,
} from '@/components/subscriptions/subscription-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePreferences } from '@/hooks/use-preferences';
import PlanLayout from '@/pages/plan/layout';
import {
    store as restartSubscription,
    destroy as stopSubscription,
} from '@/routes/subscription-activation';
import {
    store as storeSubscription,
    update as updateSubscription,
} from '@/routes/subscriptions';

type Subscription = {
    id: number;
    name: string;
    amountCents: number;
    frequency: string;
    monthlyEquivalentCents: number;
    annualCents: number;
    nextBillingDate: string | null;
    billingAnchorDate: string;
    categoryId: number;
    categoryName: string;
    subcategoryName: string | null;
    accountId: number | null;
    accountName: string | null;
    isActive: boolean;
    deactivatedOn: string | null;
    notes: string | null;
};

export default function SubscriptionsIndex({
    subscriptions,
    monthlyTotalCents,
    annualTotalCents,
    categories,
    accounts,
    frequencies,
}: {
    subscriptions: Subscription[];
    monthlyTotalCents: number;
    annualTotalCents: number;
    categories: CategoryOption[];
    accounts: AccountOption[];
    frequencies: FrequencyOption[];
}) {
    const { formatMoney, formatAmount, formatDate, today } = usePreferences();

    // One form at a time: 'new', the id being changed, or nothing open.
    const [open, setOpen] = useState<'new' | number | null>(null);

    const defaultCategory = categories.find((category) => category.isDefault);

    const blank: SubscriptionValues = {
        name: '',
        amount: '',
        frequency: frequencies[0]?.value ?? 'monthly',
        billing_anchor_date: today(),
        category_id: String(defaultCategory?.id ?? categories[0]?.id ?? ''),
        account_id: '',
        notes: '',
    };

    const valuesOf = (subscription: Subscription): SubscriptionValues => ({
        name: subscription.name,
        amount: formatAmount(subscription.amountCents),
        frequency: subscription.frequency,
        billing_anchor_date: subscription.billingAnchorDate,
        category_id: String(subscription.categoryId),
        account_id: subscription.accountId
            ? String(subscription.accountId)
            : '',
        notes: subscription.notes ?? '',
    });

    const labelFor = (frequency: string) =>
        frequencies.find((option) => option.value === frequency)?.label ??
        frequency;

    return (
        <PlanLayout
            tab="subscriptions"
            title="Subscriptions"
            description="What leaves your account on a schedule. A subscription is not tied to one year — it is planned into every year it bills in."
            action={
                <Button
                    onClick={() => setOpen(open === 'new' ? null : 'new')}
                    data-testid="add-subscription"
                >
                    <Plus className="size-4" />
                    New subscription
                </Button>
            }
        >
            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Every month
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold tabular-nums"
                                data-testid="monthly-total"
                            >
                                {formatMoney(monthlyTotalCents)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Every year
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold tabular-nums"
                                data-testid="annual-total"
                            >
                                {formatMoney(annualTotalCents)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {open === 'new' && (
                    <SubscriptionForm
                        title="A new subscription"
                        submitLabel="Add subscription"
                        url={storeSubscription.url()}
                        method="post"
                        initial={blank}
                        categories={categories}
                        accounts={accounts}
                        frequencies={frequencies}
                        onDone={() => setOpen(null)}
                    />
                )}

                {subscriptions.length === 0 ? (
                    <EmptyState
                        title="Nothing on a schedule yet"
                        message="Add what leaves your account every month and the plan will follow it."
                        icon={Repeat}
                        actionLabel="Add a subscription"
                        onAction={() => setOpen('new')}
                        testId="no-subscriptions"
                    />
                ) : (
                    <ul className="space-y-3">
                        {subscriptions.map((subscription) => (
                            <li key={subscription.id}>
                                <Card
                                    data-testid={`subscription-${subscription.id}`}
                                >
                                    <CardContent className="space-y-4">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium">
                                                    {subscription.name}
                                                    {!subscription.isActive && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="ml-2 align-middle"
                                                        >
                                                            Stopped
                                                        </Badge>
                                                    )}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {subscription.categoryName}
                                                    {subscription.subcategoryName &&
                                                        ` · ${subscription.subcategoryName}`}
                                                    {subscription.accountName &&
                                                        ` · ${subscription.accountName}`}
                                                </p>
                                            </div>

                                            <div className="text-right">
                                                <p className="font-semibold tabular-nums">
                                                    {formatMoney(
                                                        subscription.amountCents,
                                                    )}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {labelFor(
                                                        subscription.frequency,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    A month
                                                </dt>
                                                <dd
                                                    className="tabular-nums"
                                                    data-testid={`monthly-${subscription.id}`}
                                                >
                                                    {formatMoney(
                                                        subscription.monthlyEquivalentCents,
                                                    )}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-muted-foreground">
                                                    A year
                                                </dt>
                                                <dd
                                                    className="tabular-nums"
                                                    data-testid={`annual-${subscription.id}`}
                                                >
                                                    {formatMoney(
                                                        subscription.annualCents,
                                                    )}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {subscription.isActive
                                                        ? 'Next charge'
                                                        : 'Stopped on'}
                                                </dt>
                                                <dd
                                                    data-testid={`next-${subscription.id}`}
                                                >
                                                    {subscription.nextBillingDate
                                                        ? formatDate(
                                                              subscription.nextBillingDate,
                                                          )
                                                        : subscription.deactivatedOn
                                                          ? formatDate(
                                                                subscription.deactivatedOn,
                                                            )
                                                          : '—'}
                                                </dd>
                                            </div>
                                        </dl>

                                        {subscription.notes && (
                                            <p className="text-sm text-muted-foreground">
                                                {subscription.notes}
                                            </p>
                                        )}

                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setOpen(
                                                        open === subscription.id
                                                            ? null
                                                            : subscription.id,
                                                    )
                                                }
                                                data-testid={`edit-${subscription.id}`}
                                            >
                                                Change
                                            </Button>

                                            {subscription.isActive ? (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(
                                                            stopSubscription.url(
                                                                subscription.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    data-testid={`stop-${subscription.id}`}
                                                >
                                                    Stop
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.post(
                                                            restartSubscription.url(
                                                                subscription.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    data-testid={`restart-${subscription.id}`}
                                                >
                                                    <RefreshCw className="size-4" />
                                                    Start again
                                                </Button>
                                            )}
                                        </div>

                                        {open === subscription.id && (
                                            <SubscriptionForm
                                                title={`Change ${subscription.name}`}
                                                submitLabel="Save changes"
                                                url={updateSubscription.url(
                                                    subscription.id,
                                                )}
                                                method="patch"
                                                initial={valuesOf(subscription)}
                                                categories={categories}
                                                accounts={accounts}
                                                frequencies={frequencies}
                                                onDone={() => setOpen(null)}
                                            />
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </PlanLayout>
    );
}
