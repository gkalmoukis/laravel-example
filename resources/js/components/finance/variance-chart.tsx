import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import ChartDataTable from '@/components/finance/chart-data-table';
import type { VarianceRowData } from '@/components/finance/variance-row';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { usePreferences } from '@/hooks/use-preferences';

const CONFIG = {
    planned: { label: 'Plan', color: 'var(--series-plan)' },
    actual: { label: 'Actual', color: 'var(--series-actual)' },
};

/**
 * Plan against actual, category by category (CMP-01).
 *
 * The rows arrive worst-first, so the chart shows the handful that actually drifted
 * rather than every category the user has: past a dozen bars the shape stops being
 * readable and the table below is the better way to read it anyway.
 *
 * Laid out sideways because category names are words — upright they would be rotated or
 * truncated (§5.2, UX-08).
 */
export default function VarianceChart({
    rows,
    label,
}: {
    rows: VarianceRowData[];
    label: string;
}) {
    const { formatAmount } = usePreferences();

    const shown = rows.slice(0, 8);

    if (shown.length === 0) {
        return null;
    }

    const series = shown.map((row) => ({
        name: row.categoryName,
        planned: row.plannedCents,
        actual: row.actualCents,
    }));

    return (
        <ChartDataTable
            caption={`${label}: plan against actual by category`}
            columns={['Category', 'Plan', 'Actual']}
            rows={shown.map((row) => [
                row.categoryName,
                formatAmount(row.plannedCents),
                formatAmount(row.actualCents),
            ])}
        >
            <ChartContainer
                config={CONFIG}
                className="h-64 w-full"
                role="img"
                aria-label={`${label}: planned against actual by category`}
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
        </ChartDataTable>
    );
}
