import NetWorthChart, {
    type NetWorthPoint,
} from '@/components/net-worth/net-worth-chart';
import { usePreferences } from '@/hooks/use-preferences';
import { shortMonth } from '@/lib/dates';
import ChartCard from './chart-card';

export type NetWorthTrendPoint = {
    month: number;
    netCents: number;
    isRecorded: boolean;
};

const TITLE = 'Net worth';

/**
 * Net worth across the year (NW-01).
 *
 * The table says which months were actually recorded and which are the last known figure
 * carried forward, because the two are different claims (NW-04).
 */
export default function NetWorthTrendChart({
    points,
    year,
}: {
    points: NetWorthTrendPoint[];
    year: number;
}) {
    const { formatAmount, formatLocale } = usePreferences();

    const label = (month: number) =>
        month === 0 ? 'Start' : shortMonth(month, formatLocale);

    const series: NetWorthPoint[] = points.map((point) => ({
        label: label(point.month),
        net: point.netCents,
    }));

    return (
        <ChartCard
            title={TITLE}
            caption={`Net worth through ${year}`}
            columns={['Month', 'Net worth', 'Figure']}
            rows={points.map((point) => [
                label(point.month),
                formatAmount(point.netCents),
                point.isRecorded ? 'Recorded' : 'Carried forward',
            ])}
        >
            <NetWorthChart points={series} />
        </ChartCard>
    );
}
