import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AlertsPanel, { type AlertItem } from '@/components/finance/alerts-panel';
import GlossaryTerm from '@/components/finance/glossary-term';
import { Stat, StatGrid } from '@/components/finance/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePreferences } from '@/hooks/use-preferences';
import ReportsLayout from '@/pages/reports/layout';
import { index as cashFlow } from '@/routes/cash-flow';
import { index as comparison } from '@/routes/comparison';
import { index as forecast } from '@/routes/forecast';

type Series = {
    plannedCents: number;
    actualCents: number;
    forecastCents: number;
};

type Props = {
    year: number;
    completedMonths: number;
    income: Series;
    expenses: Series;
    savings: Series;
    balance: {
        openingCents: number;
        availableCents: number;
        plannedYearEndCents: number;
        forecastYearEndCents: number;
        lastActualMonth: number | null;
    };
    deviation: { cents: number; hasBaseline: boolean };
    alerts: AlertItem[];
};

export default function ReportsSummary({
    year,
    completedMonths,
    income,
    expenses,
    savings,
    balance,
    deviation,
    alerts,
}: Props) {
    const { formatMoney } = usePreferences();

    return (
        <ReportsLayout
            year={year}
            tab="summary"
            title="Summary"
            description={
                <>
                    How the year is going: what you have earned and spent, and
                    the <GlossaryTerm term="forecast">forecast</GlossaryTerm>{' '}
                    for the rest of it.
                </>
            }
        >
            <div className="space-y-6">
                <AlertsPanel alerts={alerts} />

                <StatGrid>
                    <Stat
                        label="Available now"
                        value={formatMoney(balance.availableCents)}
                        sub={`Started the year with ${formatMoney(balance.openingCents)}`}
                    />
                    <Stat
                        label="Forecast year end"
                        value={formatMoney(balance.forecastYearEndCents)}
                        tone={
                            balance.forecastYearEndCents < 0
                                ? 'bad'
                                : 'forecast'
                        }
                        sub={`Planned ${formatMoney(balance.plannedYearEndCents)}`}
                    />
                    <Stat
                        label="Saved so far"
                        value={formatMoney(savings.actualCents)}
                        tone={savings.actualCents < 0 ? 'bad' : 'actual'}
                        sub={`Planned ${formatMoney(savings.plannedCents)}`}
                    />
                    <Stat
                        label="Months signed off"
                        value={`${completedMonths} / 12`}
                        sub={
                            deviation.cents === 0
                                ? 'Exactly as planned'
                                : `${formatMoney(Math.abs(deviation.cents))} ${
                                      deviation.cents > 0 ? 'better' : 'worse'
                                  } than ${
                                      deviation.hasBaseline
                                          ? 'first planned'
                                          : 'your current plan'
                                  }`
                        }
                    />
                </StatGrid>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Money in
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SeriesRows series={income} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Money out
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SeriesRows series={expenses} />
                        </CardContent>
                    </Card>
                </div>

                <nav aria-label="Reports" className="grid gap-3 sm:grid-cols-3">
                    <Drilldown
                        href={comparison.url({ year })}
                        title="Plan vs actual"
                        body="Where the plan held and where it did not, category by category."
                    />
                    <Drilldown
                        href={cashFlow.url({ year })}
                        title="Cash flow"
                        body="What the balance does month by month, and whether it runs out."
                    />
                    <Drilldown
                        href={forecast.url({ year })}
                        title="Forecast"
                        body="Where the year lands if the rest of it goes to plan."
                    />
                </nav>
            </div>
        </ReportsLayout>
    );
}

/**
 * Plan, actual and forecast in the fixed order and the fixed language of §5.2, so the
 * three are never confused for one another.
 */
function SeriesRows({ series }: { series: Series }) {
    const { formatMoney } = usePreferences();

    const rows = [
        { label: 'Plan', value: series.plannedCents, tone: 'text-series-plan' },
        {
            label: 'Actual',
            value: series.actualCents,
            tone: 'text-series-actual',
        },
        {
            label: 'Forecast',
            value: series.forecastCents,
            tone: 'text-series-forecast',
        },
    ];

    return (
        <dl className="space-y-2 text-sm">
            {rows.map((row) => (
                <div key={row.label} className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{row.label}</dt>
                    <dd className={`font-medium tabular-nums ${row.tone}`}>
                        {formatMoney(row.value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function Drilldown({
    href,
    title,
    body,
}: {
    href: string;
    title: string;
    body: string;
}) {
    return (
        <Link
            href={href}
            className="group rounded-lg border bg-card p-4 shadow-card transition-colors hover:border-foreground/20"
        >
            <span className="flex items-center justify-between gap-2 font-medium">
                {title}
                <ArrowRight className="size-4 text-muted-foreground" />
            </span>
            <span className="mt-1 block text-sm text-muted-foreground">
                {body}
            </span>
        </Link>
    );
}
