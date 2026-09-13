import { Head, router } from '@inertiajs/react';
import { SelectedYearSetupBanner } from '@/components/finance/setup-banner';
import VarianceRow, {
    type VarianceRowData,
} from '@/components/finance/variance-row';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePreferences } from '@/hooks/use-preferences';
import AppLayout from '@/layouts/app-layout';
import { index as comparisonIndex } from '@/routes/comparison';
import type { BreadcrumbItem } from '@/types';

const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

export default function ComparisonIndex({
    year,
    mode,
    month,
    completedMonths,
    income,
    expenses,
}: {
    year: number;
    mode: string;
    month: number | null;
    completedMonths: number;
    income: VarianceRowData[];
    expenses: VarianceRowData[];
}) {
    const { formatLocale } = usePreferences();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: `${year} plan vs actual`, href: comparisonIndex({ year }) },
    ];

    const monthName = (value: number) =>
        new Intl.DateTimeFormat(formatLocale, { month: 'long' }).format(
            new Date(2000, value - 1, 1),
        );

    const show = (next: { mode?: string; month?: number }) => {
        router.get(
            comparisonIndex.url({ year }),
            {
                mode: next.mode ?? mode,
                ...(next.month ? { month: next.month } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const isYearToDate = mode === 'ytd';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Plan vs actual · ${year}`} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title="Plan vs actual"
                    description="Where the year went to plan, and where it did not."
                />

                <SelectedYearSetupBanner />

                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex gap-1 rounded-md border p-1">
                        <Button
                            size="sm"
                            variant={isYearToDate ? 'ghost' : 'default'}
                            onClick={() => show({ mode: 'month' })}
                            data-testid="mode-month"
                        >
                            One month
                        </Button>
                        <Button
                            size="sm"
                            variant={isYearToDate ? 'default' : 'ghost'}
                            onClick={() => show({ mode: 'ytd' })}
                            data-testid="mode-ytd"
                        >
                            Year to date
                        </Button>
                    </div>

                    {!isYearToDate && month !== null && (
                        <Select
                            value={String(month)}
                            onValueChange={(value) =>
                                show({ mode: 'month', month: Number(value) })
                            }
                        >
                            <SelectTrigger
                                className="w-44"
                                data-testid="month-picker"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MONTHS.map((value) => (
                                    <SelectItem
                                        key={value}
                                        value={String(value)}
                                    >
                                        {monthName(value)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {isYearToDate && (
                        <p
                            className="text-sm text-muted-foreground"
                            data-testid="ytd-basis"
                        >
                            Based on {completedMonths}{' '}
                            {completedMonths === 1
                                ? 'completed month'
                                : 'completed months'}
                            .
                        </p>
                    )}
                </div>

                {isYearToDate && completedMonths === 0 && (
                    <div
                        className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                        data-testid="nothing-completed"
                    >
                        No month has been marked complete yet, so there is
                        nothing to compare. Finish a month first.
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Expenses</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {expenses.map((row) => (
                            <VarianceRow
                                key={row.categoryId}
                                row={row}
                                year={year}
                                month={month ?? 1}
                            />
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Income</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {income.map((row) => (
                            <VarianceRow
                                key={row.categoryId}
                                row={row}
                                year={year}
                                month={month ?? 1}
                            />
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
