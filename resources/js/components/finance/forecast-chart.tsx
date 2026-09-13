import {
    CartesianGrid,
    Line,
    LineChart,
    ReferenceLine,
    XAxis,
    YAxis,
} from 'recharts';
import ChartDataTable from '@/components/finance/chart-data-table';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { usePreferences } from '@/hooks/use-preferences';
import { shortMonth } from '@/lib/dates';

export type ForecastPoint = {
    month: number;
    closingCents: number;
};

const CONFIG = {
    forecast: { label: 'Forecast', color: 'var(--series-forecast)' },
};

/**
 * Where the balance is heading, month by month (FC-02).
 *
 * Dotted, because that is what a forecast looks like everywhere in this application
 * (§5.2) — the same figure drawn solid would claim to be something that already
 * happened. The zero line is drawn because crossing it is the one thing this chart
 * exists to warn about.
 */
export default function ForecastChart({ points }: { points: ForecastPoint[] }) {
    const { formatAmount, formatLocale } = usePreferences();

    const series = points.map((point) => ({
        month: shortMonth(point.month, formatLocale),
        forecast: point.closingCents,
    }));

    return (
        <ChartDataTable
            caption="Forecast balance at the end of each month"
            columns={['Month', 'Forecast balance']}
            rows={points.map((point) => [
                shortMonth(point.month, formatLocale),
                formatAmount(point.closingCents),
            ])}
        >
            <ChartContainer
                config={CONFIG}
                className="h-64 w-full"
                aria-label="Forecast balance at the end of each month"
            >
                <LineChart data={series} margin={{ left: 8, right: 8 }}>
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
                    <ReferenceLine
                        y={0}
                        stroke="var(--status-over)"
                        strokeWidth={1}
                    />
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Line
                        dataKey="forecast"
                        stroke="var(--color-forecast)"
                        strokeDasharray="1 4"
                        strokeWidth={2}
                        dot={false}
                    />
                </LineChart>
            </ChartContainer>
        </ChartDataTable>
    );
}
