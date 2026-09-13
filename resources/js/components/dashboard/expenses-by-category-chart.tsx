import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { usePreferences } from '@/hooks/use-preferences';
import ChartCard, { ChartEmpty } from './chart-card';

export type CategorySpend = {
    categoryId: number;
    categoryName: string;
    plannedCents: number;
    actualCents: number;
};

export type ExpensesByCategory = { month: number; rows: CategorySpend[] };

const TITLE = 'Where the money went';

const CONFIG = {
    planned: { label: 'Plan', color: 'var(--series-plan)' },
    actual: { label: 'Actual', color: 'var(--series-actual)' },
};

/**
 * Plan against actual for each top-level expense category, for one month (DASH-04).
 *
 * Laid out sideways because category names are words: upright they would be rotated or
 * truncated, and a chart nobody can read the labels of is decoration.
 */
export default function ExpensesByCategoryChart({
    data,
    monthLabel,
}: {
    data: ExpensesByCategory;
    monthLabel: string;
}) {
    const { formatAmount } = usePreferences();

    if (data.rows.length === 0) {
        return (
            <ChartEmpty
                title={TITLE}
                message={`Nothing planned or spent in ${monthLabel} yet.`}
            />
        );
    }

    const series = data.rows.map((row) => ({
        name: row.categoryName,
        planned: row.plannedCents,
        actual: row.actualCents,
    }));

    return (
        <ChartCard
            title={TITLE}
            caption={`Plan against actual by category, ${monthLabel}`}
            columns={['Category', 'Plan', 'Actual']}
            rows={data.rows.map((row) => [
                row.categoryName,
                formatAmount(row.plannedCents),
                formatAmount(row.actualCents),
            ])}
        >
            <ChartContainer
                config={CONFIG}
                className="h-64 w-full"
                aria-label={`Planned against actual spending by category in ${monthLabel}`}
            >
                <BarChart
                    data={series}
                    layout="vertical"
                    margin={{ left: 8, right: 8 }}
                >
                    <CartesianGrid horizontal={false} />
                    <XAxis
                        type="number"
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(value: number) => formatAmount(value)}
                    />
                    <YAxis
                        type="category"
                        dataKey="name"
                        tickLine={false}
                        axisLine={false}
                        width={110}
                    />
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Bar
                        dataKey="planned"
                        fill="var(--color-planned)"
                        radius={2}
                    />
                    <Bar
                        dataKey="actual"
                        fill="var(--color-actual)"
                        radius={2}
                    />
                </BarChart>
            </ChartContainer>
        </ChartCard>
    );
}
