import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import GlossaryTerm from '@/components/finance/glossary-term';
import RecordCards from '@/components/finance/record-cards';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePreferences } from '@/hooks/use-preferences';
import ReportsLayout from '@/pages/reports/layout';
import { show as monthShow } from '@/routes/months';

type Summary = {
    incomeCents: number;
    expenseCents: number;
    savingsCents: number;
    yearEndCents: number;
    plannedYearEndCents: number;
    deviationCents: number;
    hasBaseline: boolean;
    baselineCapturedAt: string | null;
};

type MonthRow = {
    month: number;
    status: string;
    source: string;
    needsAttention: boolean;
    incomeCents: number;
    expenseCents: number;
    netCents: number;
    closingCents: number;
};

type CategoryRow = {
    categoryId: number;
    categoryName: string;
    plannedCents: number;
    forecastCents: number;
    differenceCents: number;
};

/**
 * The share of income kept, computed here rather than server-side: the rule is full
 * precision, rounded only for display — and there is no rate at all without income
 * (EDGE-03).
 */
function savingsRate(savings: number, income: number): string {
    if (income === 0) {
        return '—';
    }

    return `${((savings / income) * 100).toFixed(1)}%`;
}

export default function ForecastIndex({
    year,
    summary,
    months,
    categories,
}: {
    year: number;
    summary: Summary;
    months: MonthRow[];
    categories: { income: CategoryRow[]; expenses: CategoryRow[] };
}) {
    const { formatMoney, formatAmount, formatDate, formatLocale } =
        usePreferences();

    const monthName = (month: number) =>
        new Intl.DateTimeFormat(formatLocale, { month: 'long' }).format(
            new Date(year, month - 1, 1),
        );

    const unfinished = months.filter((month) => month.needsAttention);

    const categoryTable = (rows: CategoryRow[], heading: string) => (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{heading}</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Category</TableHead>
                            <TableHead className="text-right">Plan</TableHead>
                            <TableHead className="text-right">
                                Forecast
                            </TableHead>
                            <TableHead className="text-right">
                                Difference
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow
                                key={row.categoryId}
                                data-testid={`forecast-category-${row.categoryId}`}
                            >
                                <TableCell>{row.categoryName}</TableCell>
                                <TableCell className="text-right text-series-plan tabular-nums">
                                    {formatAmount(row.plannedCents)}
                                </TableCell>
                                <TableCell className="text-right text-series-forecast tabular-nums">
                                    {formatAmount(row.forecastCents)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatAmount(row.differenceCents)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );

    return (
        <ReportsLayout
            year={year}
            tab="forecast"
            title="Forecast"
            description={
                <>
                    The <GlossaryTerm term="forecast">forecast</GlossaryTerm>{' '}
                    for the whole year, if the rest of it goes to{' '}
                    <GlossaryTerm term="plan">plan</GlossaryTerm>.
                </>
            }
        >
            <div className="space-y-6">
                <dl className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Income
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums">
                            {formatMoney(summary.incomeCents)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Expenses
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums">
                            {formatMoney(summary.expenseCents)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Savings
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums">
                            {formatMoney(summary.savingsCents)}
                        </dd>
                        <dd
                            className="text-xs text-muted-foreground"
                            data-testid="savings-rate"
                        >
                            {savingsRate(
                                summary.savingsCents,
                                summary.incomeCents,
                            )}{' '}
                            of what you earn
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Year end balance
                        </dt>
                        <dd className="mt-1 font-semibold text-series-forecast tabular-nums">
                            {formatMoney(summary.yearEndCents)}
                        </dd>
                        <dd
                            className="text-xs text-muted-foreground"
                            data-testid="deviation"
                        >
                            {summary.deviationCents === 0
                                ? 'Exactly as planned'
                                : `${formatMoney(Math.abs(summary.deviationCents))} ${
                                      summary.deviationCents > 0
                                          ? 'better'
                                          : 'worse'
                                  } than ${
                                      summary.hasBaseline
                                          ? 'first planned'
                                          : 'your current plan'
                                  }`}
                            {summary.hasBaseline &&
                                summary.baselineCapturedAt && (
                                    <>
                                        {' '}
                                        (set{' '}
                                        {formatDate(summary.baselineCapturedAt)}
                                        )
                                    </>
                                )}
                        </dd>
                    </div>
                </dl>

                {unfinished.length > 0 && (
                    <div
                        className="flex flex-wrap items-center gap-2 rounded-md border border-status-warning/40 p-4 text-sm"
                        data-testid="unfinished-months"
                    >
                        <AlertTriangle
                            className="size-4 text-status-warning"
                            aria-hidden="true"
                        />
                        <span>
                            {unfinished
                                .map((month) => monthName(month.month))
                                .join(', ')}{' '}
                            {unfinished.length === 1 ? 'is' : 'are'} not marked
                            complete, so the forecast still uses your plan
                            {unfinished.length === 1 ? ' for it' : ' for them'}.
                        </span>
                        <Link
                            className="underline underline-offset-4"
                            href={monthShow({
                                year,
                                month: unfinished[0].month,
                            })}
                        >
                            Review {monthName(unfinished[0].month)}
                        </Link>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Month by month
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="md:hidden">
                        <RecordCards
                            items={months.map((month) => ({
                                key: month.month,
                                title: (
                                    <span className="flex flex-wrap items-center gap-2">
                                        {monthName(month.month)}
                                        <Badge
                                            variant="outline"
                                            className={
                                                month.needsAttention
                                                    ? 'border-status-warning/40 text-status-warning'
                                                    : ''
                                            }
                                        >
                                            {month.source}
                                        </Badge>
                                    </span>
                                ),
                                fields: [
                                    {
                                        label: 'In',
                                        value: formatAmount(month.incomeCents),
                                    },
                                    {
                                        label: 'Out',
                                        value: formatAmount(month.expenseCents),
                                    },
                                    {
                                        label: 'Net',
                                        value: formatAmount(month.netCents),
                                    },
                                    {
                                        label: 'Balance',
                                        value: formatAmount(month.closingCents),
                                    },
                                ],
                            }))}
                            testId="forecast-month-cards"
                        />
                    </CardContent>

                    <CardContent className="hidden overflow-x-auto md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Month</TableHead>
                                    <TableHead>Based on</TableHead>
                                    <TableHead className="text-right">
                                        In
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Out
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Net
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {months.map((month) => (
                                    <TableRow
                                        key={month.month}
                                        data-testid={`forecast-month-${month.month}`}
                                    >
                                        <TableCell>
                                            {monthName(month.month)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    month.needsAttention
                                                        ? 'border-status-warning/40 text-status-warning'
                                                        : ''
                                                }
                                            >
                                                {month.source}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatAmount(month.incomeCents)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatAmount(month.expenseCents)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatAmount(month.netCents)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatAmount(month.closingCents)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {categoryTable(categories.expenses, 'Expenses by category')}
                {categoryTable(categories.income, 'Income by category')}
            </div>
        </ReportsLayout>
    );
}
