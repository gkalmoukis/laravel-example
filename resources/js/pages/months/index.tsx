import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import MonthStatusBadge from '@/components/finance/month-status-badge';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePreferences } from '@/hooks/use-preferences';
import AppLayout from '@/layouts/app-layout';
import { index as monthsIndex } from '@/routes/months';
import { index as transactionsIndex } from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';

type MonthCard = {
    month: number;
    status: string;
    incomeCents: number;
    expenseCents: number;
    netCents: number;
    issueCount: number;
};

function monthName(month: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, { month: 'long' }).format(
        new Date(2000, month - 1, 1),
    );
}

export default function MonthsIndex({
    year,
    months,
}: {
    year: number;
    months: MonthCard[];
}) {
    const { formatMoney, formatLocale } = usePreferences();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: `${year} months`, href: monthsIndex({ year }) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Months · ${year}`} />

            <div className="px-4 py-6">
                <Heading
                    title={`${year} month by month`}
                    description="Where each month stands, and what is left to finish."
                />

                <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {months.map((month) => (
                        <Card
                            key={month.month}
                            data-testid={`month-${month.month}`}
                        >
                            <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                                <CardTitle className="text-base">
                                    {monthName(month.month, formatLocale)}
                                </CardTitle>
                                <MonthStatusBadge status={month.status} />
                            </CardHeader>

                            <CardContent className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Income
                                    </span>
                                    <span className="tabular-nums">
                                        {formatMoney(month.incomeCents)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Expenses
                                    </span>
                                    <span className="tabular-nums">
                                        {formatMoney(month.expenseCents)}
                                    </span>
                                </div>
                                <div className="flex justify-between font-medium">
                                    <span>Net</span>
                                    <span className="tabular-nums">
                                        {formatMoney(month.netCents)}
                                    </span>
                                </div>

                                {month.issueCount > 0 && (
                                    <Link
                                        className="mt-2 flex items-center gap-1 text-amber-700 underline underline-offset-4 dark:text-amber-400"
                                        href={transactionsIndex.url({
                                            query: {
                                                month: String(month.month),
                                                year: String(year),
                                                issues: '1',
                                            },
                                        })}
                                        data-testid={`issues-${month.month}`}
                                    >
                                        <AlertTriangle
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        {month.issueCount}{' '}
                                        {month.issueCount === 1
                                            ? 'transaction needs'
                                            : 'transactions need'}{' '}
                                        a look
                                    </Link>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
