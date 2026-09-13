import BalanceChart, {
    type BalancePoint,
} from '@/components/finance/balance-chart';
import ChartDataTable from '@/components/finance/chart-data-table';
import GlossaryTerm from '@/components/finance/glossary-term';
import RecordCards from '@/components/finance/record-cards';
import { Stat, StatGrid } from '@/components/finance/stat-card';
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
        <ReportsLayout
            year={year}
            tab="cash-flow"
            title="Cash flow"
            description={
                <>
                    What your{' '}
                    <GlossaryTerm term="cashFlow">cash flow</GlossaryTerm> does
                    over the year, and whether the{' '}
                    <GlossaryTerm term="closingBalance">
                        closing balance
                    </GlossaryTerm>{' '}
                    ever runs out.
                </>
            }
        >
            <div className="space-y-6">
                <StatGrid>
                    <Stat
                        label="Started the year with"
                        value={formatMoney(openingBalanceCents)}
                    />
                    <Stat
                        label="Available now"
                        value={formatMoney(currentAvailableCents)}
                    />
                    <Stat
                        label="Year end, planned"
                        value={formatMoney(plannedYearEndCents)}
                        tone="plan"
                    />
                    <Stat
                        label="Year end, forecast"
                        value={formatMoney(forecastYearEndCents)}
                        tone="forecast"
                    />
                </StatGrid>

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
                    <CardContent className="md:hidden">
                        <RecordCards
                            items={months.map((row) => {
                                const line = row.actual ?? row.forecast;

                                return {
                                    key: row.month,
                                    title: (
                                        <>
                                            {shortMonth(row.month)}
                                            {!row.actual && (
                                                <span className="ml-1 text-xs text-series-forecast">
                                                    forecast
                                                </span>
                                            )}
                                        </>
                                    ),
                                    fields: [
                                        {
                                            label: 'Opening',
                                            value: formatAmount(
                                                line.openingCents,
                                            ),
                                        },
                                        {
                                            label: 'In',
                                            value: formatAmount(
                                                line.incomeCents,
                                            ),
                                        },
                                        {
                                            label: 'Out',
                                            value: formatAmount(
                                                line.expenseCents,
                                            ),
                                        },
                                        {
                                            label: 'Net',
                                            value: formatAmount(line.netCents),
                                        },
                                        {
                                            label: 'Closing',
                                            value: formatAmount(
                                                line.closingCents,
                                            ),
                                        },
                                    ],
                                };
                            })}
                            testId="cash-flow-cards"
                        />
                    </CardContent>

                    <CardContent className="hidden overflow-x-auto md:block">
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
        </ReportsLayout>
    );
}
