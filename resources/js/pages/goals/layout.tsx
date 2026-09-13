import { Head } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import Heading from '@/components/heading';
import PageShell from '@/components/page-shell';
import YearNav from '@/components/planning/year-nav';
import AppLayout from '@/layouts/app-layout';
import { show as emergencyFund } from '@/routes/emergency-fund';
import { index as goals } from '@/routes/goals';
import { index as netWorth } from '@/routes/net-worth';
import type { BreadcrumbItem } from '@/types';

export type GoalsTab = 'goals' | 'emergency-fund' | 'net-worth';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Goals', href: goals() }];

/**
 * The shell the three "what you are working towards" screens share.
 *
 * Goals, the emergency fund and net worth answer the same question at three distances —
 * what you are saving for, what protects you, and where you stand — so they are tabs of
 * one destination rather than three entries in the sidebar (UX-09).
 *
 * Each tab keeps its own title: unlike the reports hub these are not three views of one
 * subject, and "Goals" over the net worth figures would simply be wrong.
 */
export default function GoalsLayout({
    tab,
    title,
    description,
    action,
    children,
}: PropsWithChildren<{
    tab: GoalsTab;
    title: string;
    description?: ReactNode;
    action?: ReactNode;
}>) {
    const tabs = [
        { key: 'goals', label: 'Goals', href: goals.url() },
        {
            key: 'emergency-fund',
            label: 'Emergency fund',
            href: emergencyFund.url(),
        },
        { key: 'net-worth', label: 'Net worth', href: netWorth.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <PageShell stacked={false}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading title={title} description={description} />

                    {action}
                </div>

                <div className="mt-4">
                    <YearNav current={tab} steps={tabs} label="Goals" />
                </div>

                <div className="mt-8">{children}</div>
            </PageShell>
        </AppLayout>
    );
}
