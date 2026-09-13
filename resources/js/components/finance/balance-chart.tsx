import {
    CartesianGrid,
    Line,
    LineChart,
    ReferenceLine,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { usePreferences } from '@/hooks/use-preferences';

export type BalancePoint = {
    month: string;
    planned: number;
    forecast: number;
    actual: number | null;
};

/**
 * The three closing-balance series (CF-02).
 *
 * Each series has its own line style as well as its own colour — dashed for the plan,
 * solid for what really happened, dotted for the forecast — so the three stay apart in
 * black and white and for a colour-blind reader (§5.2, FE-14).
 *
 * The zero line is drawn because crossing it is the single most important thing this
 * chart can show: it is the month the user would run out of money.
 */
const CONFIG = {
    planned: { label: 'Plan', color: 'var(--series-plan)' },
    actual: { label: 'Actual', color: 'var(--series-actual)' },
    forecast: { label: 'Forecast', color: 'var(--series-forecast)' },
};

export default function BalanceChart({ points }: { points: BalancePoint[] }) {
    const { formatAmount } = usePreferences();

    return (
        <ChartContainer
            config={CONFIG}
            className="h-64 w-full"
            aria-label="Closing balance each month, for the plan, what really happened, and the forecast"
        >
            <LineChart data={points} margin={{ left: 8, right: 8 }}>
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
                    dataKey="planned"
                    stroke="var(--color-planned)"
                    strokeDasharray="6 4"
                    strokeWidth={2}
                    dot={false}
                />
                <Line
                    dataKey="forecast"
                    stroke="var(--color-forecast)"
                    strokeDasharray="1 4"
                    strokeWidth={2}
                    dot={false}
                />
                <Line
                    dataKey="actual"
                    stroke="var(--color-actual)"
                    strokeWidth={3}
                    dot={false}
                    connectNulls={false}
                />
            </LineChart>
        </ChartContainer>
    );
}
