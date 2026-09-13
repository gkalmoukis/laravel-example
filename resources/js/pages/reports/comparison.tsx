import { router } from '@inertiajs/react';
import GlossaryTerm from '@/components/finance/glossary-term';
import VarianceChart from '@/components/finance/variance-chart';
import VarianceRow, {
    type VarianceRowData,
} from '@/components/finance/variance-row';
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
import ReportsLayout from '@/pages/reports/layout';
import { index as comparisonIndex } from '@/routes/comparison';

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
        <ReportsLayout
            year={year}
            tab="comparison"
            title="Plan vs actual"
            description={
                <>
                    Where the <GlossaryTerm term="actual">actual</GlossaryTerm>{' '}
                    matched the <GlossaryTerm term="plan">plan</GlossaryTerm>,
                    and where the{' '}
                    <GlossaryTerm term="variance">variance</GlossaryTerm> is
                    worth a look.
                </>
            }
        >
            <div className="space-y-6">
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
                    <CardContent className="space-y-4">
                        <VarianceChart rows={expenses} label="Expenses" />

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
                    <CardContent className="space-y-4">
                        <VarianceChart rows={income} label="Income" />

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
        </ReportsLayout>
    );
}
