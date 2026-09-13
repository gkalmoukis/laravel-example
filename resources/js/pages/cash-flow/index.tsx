import { Head } from '@inertiajs/react';
import BalanceChart, {
    type BalancePoint,
} from '@/components/finance/balance-chart';
import ChartDataTable from '@/components/finance/chart-data-table';
import Heading from '@/components/heading';
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
import AppLayout from '@/layouts/app-layout';
import { index as cashFlowIndex } from '@/routes/cash-flow';
import type { BreadcrumbItem } from '@/types';

type Line = {
    openingCents: number;
    incomeCents: number;
    expenseCents: number;
    netCents: number;
    closingCents: number;
};

type MonthRow = {
    month: number;
    status: string;
    planned: Line;
    forecast: Line;
    actual: Line | null;
};

export default function CashFlowIndex({
    year,
    openingBalanceCents,
    currentAvailableCents,
    plannedYearEndCents,
    forecastYearEndCents,
    months,
}: {
    year: number;
    openingBalanceCents: number;
    lastActualMonth: number | null;
    currentAvailableCents: number;
    plannedYearEndCents: number;
    forecastYearEndCents: number;
    months: MonthRow[];
}) {
    const { formatMoney, formatAmount, formatLocale } = usePreferences();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: `${year} cash flow`, href: cashFlowIndex({ year }) },
    ];

    const shortMonth = (month: number) =>
        new Intl.DateTimeFormat(formatLocale, { month: 'short' }).format(
            new Date(2000, month - 1, 1),
        );

    const points: BalancePoint[] = months.map((row) => ({
        month: shortMonth(row.month),
        planned: row.planned.closingCents,
        forecast: row.forecast.closingCents,
        actual: row.actual?.closingCents ?? null,
    }));

    const tableRows = months.map((row) => [
        shortMonth(row.month),
        formatAmount(row.planned.closingCents),
        row.actual ? formatAmount(row.actual.closingCents) : '—',
        formatAmount(row.forecast.closingCents),
    ]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Cash flow · ${year}`} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title="Cash flow"
                    description="What the balance does over the year — and whether it ever runs out."
                />

                <dl className="grid gap-4 rounded-lg border p-4 sm:grid-cols-4">
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Started the year with
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums">
                            {formatMoney(openingBalanceCents)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Available now
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums">
                            {formatMoney(currentAvailableCents)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Year end, planned
                        </dt>
                        <dd className="mt-1 font-semibold text-series-plan tabular-nums">
                            {formatMoney(plannedYearEndCents)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Year end, forecast
                        </dt>
                        <dd className="mt-1 font-semibold text-series-forecast tabular-nums">
                            {formatMoney(forecastYearEndCents)}
                        </dd>
                    </div>
                </dl>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Closing balance each month
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ChartDataTable
                            caption={`Closing balance each month of ${year}`}
                            columns={['Month', 'Plan', 'Actual', 'Forecast']}
                            rows={tableRows}
                        >
                            <BalanceChart points={points} />
                        </ChartDataTable>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Month by month
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Month</TableHead>
                                    <TableHead className="text-right">
                                        Opening
                                    </TableHead>
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
                                        Closing
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {months.map((row) => {
                                    // Actual where it exists, forecast where it does not
                                    // — and the row says which it is showing.
                                    const line = row.actual ?? row.forecast;

                                    return (
                                        <TableRow
                                            key={row.month}
                                            data-testid={`cash-flow-${row.month}`}
                                        >
                                            <TableCell>
                                                {shortMonth(row.month)}
                                                {!row.actual && (
                                                    <span className="ml-1 text-xs text-series-forecast">
                                                        forecast
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatAmount(
                                                    line.openingCents,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatAmount(line.incomeCents)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatAmount(
                                                    line.expenseCents,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatAmount(line.netCents)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatAmount(
                                                    line.closingCents,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
