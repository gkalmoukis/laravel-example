import BalanceChart, {
    type BalancePoint,
} from '@/components/finance/balance-chart';
import { usePreferences } from '@/hooks/use-preferences';
import { shortMonth } from '@/lib/dates';
import ChartCard from './chart-card';

export type ClosingBalancePoint = {
    month: number;
    plannedCents: number;
    forecastCents: number;
    actualCents: number | null;
};

const TITLE = 'Closing balance';

/**
 * Where the balance ends each month, planned against real against forecast (CF-02).
 */
export default function ClosingBalanceChart({
    points,
    year,
}: {
    points: ClosingBalancePoint[];
    year: number;
}) {
    const { formatAmount, formatLocale } = usePreferences();

    const series: BalancePoint[] = points.map((point) => ({
        month: shortMonth(point.month, formatLocale),
        planned: point.plannedCents,
        forecast: point.forecastCents,
        actual: point.actualCents,
    }));

    return (
        <ChartCard
            title={TITLE}
            caption={`Closing balance each month of ${year}`}
            columns={['Month', 'Plan', 'Actual', 'Forecast']}
            rows={points.map((point) => [
                shortMonth(point.month, formatLocale),
                formatAmount(point.plannedCents),
                point.actualCents === null
                    ? '—'
                    : formatAmount(point.actualCents),
                formatAmount(point.forecastCents),
            ])}
        >
            <BalanceChart points={series} />
        </ChartCard>
    );
}
