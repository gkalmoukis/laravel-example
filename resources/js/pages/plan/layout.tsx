import { Head, usePage } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import SetupBanner, {
    SelectedYearSetupBanner,
} from '@/components/finance/setup-banner';
import Heading from '@/components/heading';
import YearNav from '@/components/planning/year-nav';
import AppLayout from '@/layouts/app-layout';
import { show as showPlan } from '@/routes/plan';
import { index as subscriptionsIndex } from '@/routes/subscriptions';
import type { BreadcrumbItem } from '@/types';

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
    children,
}: PropsWithChildren<{
    year?: PlanYear | null;
    tab: string;
    tabs?: string[];
    title: string;
    description: ReactNode;
    action?: ReactNode;
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

            <div className="px-4 py-6">
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
                </div>

                <div className="mt-8">{children}</div>
            </div>
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
