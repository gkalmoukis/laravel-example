import { Bar, BarChart, CartesianGrid, Cell, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { usePreferences } from '@/hooks/use-preferences';
import { shortMonth } from '@/lib/dates';
import ChartCard from './chart-card';

export type MonthFlow = {
    month: number;
    incomeCents: number;
    expenseCents: number;
    isActual: boolean;
};

const TITLE = 'Month by month';

const CONFIG = {
    income: { label: 'Income', color: 'var(--status-ok)' },
    expense: { label: 'Expenses', color: 'var(--status-over)' },
};

/**
 * What came in and what went out each month (DASH-04).
 *
 * A finished month reports what happened; every other month reports what is expected.
 * Forecast bars are drawn faded and the table says which is which in words, because a
 * forecast presented as a fact is the one mistake this chart must not make (§5.2).
 */
export default function IncomeExpenseChart({
    points,
    year,
}: {
    points: MonthFlow[];
    year: number;
}) {
    const { formatAmount, formatLocale } = usePreferences();

    const series = points.map((point) => ({
        month: shortMonth(point.month, formatLocale),
        income: point.incomeCents,
        expense: point.expenseCents,
        isActual: point.isActual,
    }));

    return (
        <ChartCard
            title={TITLE}
            caption={`Income and expenses each month of ${year}`}
            columns={['Month', 'Income', 'Expenses', 'Source']}
            rows={points.map((point) => [
                shortMonth(point.month, formatLocale),
                formatAmount(point.incomeCents),
                formatAmount(point.expenseCents),
                point.isActual ? 'Actual' : 'Forecast',
            ])}
        >
            <ChartContainer
                config={CONFIG}
                className="h-64 w-full"
                role="img"
                aria-label={`Income and expenses each month of ${year}, actual for finished months and forecast for the rest`}
            >
                <BarChart data={series} margin={{ left: 8, right: 8 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="month"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        width={80}
                        tickFormatter={(value: number) => formatAmount(value)}
                    />
                    <ChartTooltip content={<ChartTooltipContent />} />

                    <Bar dataKey="income" radius={2}>
                        {series.map((point) => (
                            <Cell
                                key={`income-${point.month}`}
                                fill="var(--color-income)"
                                fillOpacity={point.isActual ? 1 : 0.4}
                            />
                        ))}
                    </Bar>

                    <Bar dataKey="expense" radius={2}>
                        {series.map((point) => (
                            <Cell
                                key={`expense-${point.month}`}
                                fill="var(--color-expense)"
                                fillOpacity={point.isActual ? 1 : 0.4}
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ChartContainer>
        </ChartCard>
    );
}
