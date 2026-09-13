import { Head, Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import SetupBanner, {
    SelectedYearSetupBanner,
} from '@/components/finance/setup-banner';
import { Stat, StatGrid } from '@/components/finance/stat-card';
import Heading from '@/components/heading';
import PageShell from '@/components/page-shell';
import YearNav from '@/components/planning/year-nav';
import { usePreferences } from '@/hooks/use-preferences';
import AppLayout from '@/layouts/app-layout';
import { show as showPlan } from '@/routes/plan';
import { index as subscriptionsIndex } from '@/routes/subscriptions';
import type { BreadcrumbItem } from '@/types';

export type PlanTotals = {
    incomeCents: number;
    expensesCents: number;
    savingsCents: number;
    yearEndCents: number;
};

export type PlanYear = {
    year: number;
    isSetupComplete: boolean;
    hasBaseline: boolean;
    baselineCapturedAt: string | null;
};

const tabLabels: Record<string, string> = {
    income: 'Income',
    expenses: 'Monthly budget',
    irregular: 'Irregular',
    opening: 'Opening position',
};

/**
 * The shell every plan tab shares: the year, its tabs, and the reminder to finish setup
 * if it was never completed.
 *
 * Subscriptions sit here too. They are not year-scoped — one subscription bills across
 * every year, and `SyncSubscriptionPlanItems` fans it into each one — so that tab carries
 * no year in its address and the year-scoped tabs beside it take theirs from whichever
 * year is selected. A user with no plan at all still reaches subscriptions; they simply
 * have no other tab to move to yet.
 */
export default function PlanLayout({
    year = null,
    tab,
    tabs = Object.keys(tabLabels),
    title,
    description,
    action,
    planTotals,
    emptyTabs = [],
    children,
}: PropsWithChildren<{
    year?: PlanYear | null;
    tab: string;
    tabs?: string[];
    title: string;
    description: ReactNode;
    action?: ReactNode;
    planTotals?: PlanTotals;
    emptyTabs?: string[];
}>) {
    const { selectedYear } = usePage().props;

    const linkYear = year?.year ?? selectedYear;

    const breadcrumbs: BreadcrumbItem[] = [
        linkYear === null
            ? { title, href: subscriptionsIndex() }
            : {
                  title: `${linkYear} plan`,
                  href: showPlan({ year: linkYear, tab: 'income' }),
              },
    ];

    const steps = [
        ...(linkYear === null
            ? []
            : tabs.map((key) => ({
                  key,
                  label: tabLabels[key] ?? key,
                  href: showPlan.url({ year: linkYear, tab: key }),
              }))),
        {
            key: 'subscriptions',
            label: 'Subscriptions',
            href: subscriptionsIndex.url(),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={year ? `${title} · ${year.year}` : title} />

            <PageShell stacked={false}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title={year ? `${year.year} plan` : title}
                        description={description}
                    />

                    {action}
                </div>

                <div className="mt-4 space-y-4">
                    <SetupReminder year={year} />

                    <YearNav current={tab} steps={steps} />

                    {planTotals && (
                        <PlanTotalsRail
                            totals={planTotals}
                            emptyTabs={emptyTabs}
                            year={linkYear}
                        />
                    )}
                </div>

                <div className="mt-8">{children}</div>
            </PageShell>
        </AppLayout>
    );
}

/**
 * The reminder to finish setting the year up, from whichever source this tab has: the
 * year its own props named, or the selected one when the tab carries no year at all.
 */
function SetupReminder({ year }: { year: PlanYear | null }) {
    if (year === null) {
        return <SelectedYearSetupBanner />;
    }

    if (year.isSetupComplete) {
        return null;
    }

    return <SetupBanner year={year.year} />;
}

/**
 * What the plan adds up to, on every tab rather than only the one it was first built
 * for. The primary block of the plan is its annual totals (UX-05).
 */
function PlanTotalsRail({
    totals,
    emptyTabs,
    year,
}: {
    totals: PlanTotals;
    emptyTabs: string[];
    year: number | null;
}) {
    const { formatMoney } = usePreferences();

    return (
        <div className="space-y-2" data-testid="plan-totals">
            <StatGrid>
                <Stat
                    label="Planned income"
                    value={formatMoney(totals.incomeCents)}
                    tone="plan"
                />
                <Stat
                    label="Planned expenses"
                    value={formatMoney(totals.expensesCents)}
                    tone="plan"
                />
                <Stat
                    label="Planned savings"
                    value={formatMoney(totals.savingsCents)}
                    tone={totals.savingsCents < 0 ? 'bad' : 'plan'}
                />
                <Stat
                    label="Balance at the end of the year"
                    value={formatMoney(totals.yearEndCents)}
                    tone={totals.yearEndCents < 0 ? 'bad' : 'plan'}
                />
            </StatGrid>

            <NextStep emptyTabs={emptyTabs} year={year} />
        </div>
    );
}

/**
 * The first tab with nothing in it, named rather than left to be found.
 */
function NextStep({
    emptyTabs,
    year,
}: {
    emptyTabs: string[];
    year: number | null;
}) {
    const next = emptyTabs[0];

    if (next === undefined || year === null) {
        return null;
    }

    return (
        <p
            className="text-sm text-muted-foreground"
            data-testid="plan-next-step"
        >
            Nothing in {tabLabels[next]?.toLowerCase() ?? next} yet.{' '}
            <Link
                className="underline underline-offset-4"
                href={showPlan({ year, tab: next })}
            >
                Add it
            </Link>
        </p>
    );
}
