import {
    Area,
    AreaChart,
    CartesianGrid,
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

export type NetWorthPoint = {
    label: string;
    net: number;
};

const CONFIG = {
    net: { label: 'Net worth', color: 'var(--series-actual)' },
};

/**
 * Net worth across the year (NW-01).
 *
 * The zero line is drawn because crossing it changes what the number means: above it the
 * user owns more than they owe, below it the reverse.
 */
export default function NetWorthChart({ points }: { points: NetWorthPoint[] }) {
    const { formatAmount } = usePreferences();

    return (
        <ChartContainer
            config={CONFIG}
            className="h-64 w-full"
            aria-label="Net worth at the end of each month"
        >
            <AreaChart data={points} margin={{ left: 8, right: 8 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="label"
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
                <Area
                    dataKey="net"
                    stroke="var(--color-net)"
                    fill="var(--color-net)"
                    fillOpacity={0.15}
                    strokeWidth={2}
                />
            </AreaChart>
        </ChartContainer>
    );
}
