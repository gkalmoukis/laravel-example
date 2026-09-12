import { Head } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import SetupBanner from '@/components/finance/setup-banner';
import Heading from '@/components/heading';
import YearNav from '@/components/planning/year-nav';
import AppLayout from '@/layouts/app-layout';
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
 */
export default function PlanLayout({
    year,
    tab,
    tabs,
    title,
    description,
    children,
}: PropsWithChildren<{
    year: PlanYear;
    tab: string;
    tabs: string[];
    title: string;
    description: string;
}>) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: `${year.year} plan`, href: `/years/${year.year}/plan/income` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} · ${year.year}`} />

            <div className="px-4 py-6">
                <Heading
                    title={`${year.year} plan`}
                    description={description}
                />

                {!year.isSetupComplete && (
                    <div className="mt-4">
                        <SetupBanner year={year.year} />
                    </div>
                )}

                <div className="mt-4">
                    <YearNav
                        current={tab}
                        steps={tabs.map((key) => ({
                            key,
                            label: tabLabels[key] ?? key,
                            href: `/years/${year.year}/plan/${key}`,
                        }))}
                    />
                </div>

                <div className="mt-8">{children}</div>
            </div>
        </AppLayout>
    );
}
