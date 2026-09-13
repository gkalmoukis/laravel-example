import { Head } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import { SelectedYearSetupBanner } from '@/components/finance/setup-banner';
import Heading from '@/components/heading';
import PageShell from '@/components/page-shell';
import YearNav from '@/components/planning/year-nav';
import AppLayout from '@/layouts/app-layout';
import { index as cashFlow } from '@/routes/cash-flow';
import { index as comparison } from '@/routes/comparison';
import { index as forecast } from '@/routes/forecast';
import { index as reports } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

export type ReportTab = 'summary' | 'comparison' | 'cash-flow' | 'forecast';

/**
 * The shell every report shares: the year, its tabs, and the reminder to finish setup.
 *
 * Plan vs actual, cash flow and forecast read the same monthly figures and answer three
 * sides of one question, so they are tabs of one destination rather than three entries
 * competing for their own place in the sidebar (UX-09).
 */
export default function ReportsLayout({
    year,
    tab,
    title,
    description,
    children,
}: PropsWithChildren<{
    year: number;
    tab: ReportTab;
    title: string;
    description: ReactNode;
}>) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: `${year} reports`, href: comparison({ year }) },
    ];

    const tabs = [
        { key: 'summary', label: 'Summary', href: reports.url({ year }) },
        {
            key: 'comparison',
            label: 'Plan vs actual',
            href: comparison.url({ year }),
        },
        { key: 'cash-flow', label: 'Cash flow', href: cashFlow.url({ year }) },
        { key: 'forecast', label: 'Forecast', href: forecast.url({ year }) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} · ${year}`} />

            <PageShell stacked={false}>
                <Heading title={`${year} reports`} description={description} />

                {/*
                 * The banner draws nothing once the year is finished, so it contributes
                 * no stray gap here on the years that do not need it (EDGE-02).
                 */}
                <div className="mt-4 space-y-4">
                    <SelectedYearSetupBanner />

                    <YearNav current={tab} steps={tabs} label="Reports" />
                </div>

                <div className="mt-8">{children}</div>
            </PageShell>
        </AppLayout>
    );
}
