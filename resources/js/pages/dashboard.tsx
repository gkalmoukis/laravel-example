import { Deferred, Head, type InertiaLinkProps, Link } from '@inertiajs/react';
import { ChartSkeleton } from '@/components/dashboard/chart-card';
import ClosingBalanceChart, {
    type ClosingBalancePoint,
} from '@/components/dashboard/closing-balance-chart';
import ExpensesByCategoryChart, {
    type ExpensesByCategory,
} from '@/components/dashboard/expenses-by-category-chart';
import GoalsProgressChart, {
    type GoalBar,
} from '@/components/dashboard/goals-progress-chart';
import IncomeExpenseChart, {
    type MonthFlow,
} from '@/components/dashboard/income-expense-chart';
import NetWorthTrendChart, {
    type NetWorthTrendPoint,
} from '@/components/dashboard/net-worth-trend-chart';
import AlertsPanel, { type AlertItem } from '@/components/finance/alerts-panel';
import MetricCard, { MetricRows } from '@/components/finance/metric-card';
import ProgressBar from '@/components/finance/progress-bar';
import Heading from '@/components/heading';
import { usePreferences } from '@/hooks/use-preferences';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as cashFlow } from '@/routes/cash-flow';
import { index as comparison } from '@/routes/comparison';
import { show as emergencyFund } from '@/routes/emergency-fund';
import { index as forecast } from '@/routes/forecast';
import { index as months } from '@/routes/months';
import { index as netWorth } from '@/routes/net-worth';
import { index as transactions } from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';

type Trio = {
    actualCents: number;
    plannedCents: number;
    forecastCents: number;
};

type Props = {
    year: number;
    currentAvailableCents: number;
    yearEnd: {
        forecastCents: number;
        plannedCents: number;
        deviationCents: number;
        hasBaseline: boolean;
    };
    income: Trio;
    expenses: Trio;
    savings: Trio & {
        actualIncomeCents: number;
        plannedIncomeCents: number;
    };
    emergencyFund: {
        currentCents: number;
        targetCents: number;
        isReached: boolean;
        hasBeenStarted: boolean;
        monthsToTarget: number | null;
    };
    netWorth: { currentCents: number; changeCents: number | null };
    completedMonths: number;
    transactionIssues: number;
    alerts: AlertItem[];

    // Deferred: absent on the first response, and along a moment later (DASH-04, FE-08).
    closingBalance?: ClosingBalancePoint[];
    expensesByCategory?: ExpensesByCategory;
    incomeAndExpenses?: MonthFlow[];
    netWorthTrend?: NetWorthTrendPoint[];
    goalsProgress?: GoalBar[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

/**
 * A share written as a percentage, or a dash when there is nothing to divide by. The
 * figures cross the wire as cents and are divided here, so nothing is rounded twice.
 */
function rate(part: number, whole: number): string {
    if (whole <= 0) {
        return '—';
    }

    return `${Math.round((part / whole) * 1000) / 10}%`;
}

export default function Dashboard({
    year,
    currentAvailableCents,
    yearEnd,
    income,
    expenses,
    savings,
    emergencyFund: fund,
    netWorth: worth,
    completedMonths,
    transactionIssues,
    alerts,
    closingBalance,
    expensesByCategory,
    incomeAndExpenses,
    netWorthTrend,
    goalsProgress,
}: Props) {
    const { formatMoney, formatLocale } = usePreferences();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={`${year} at a glance`}
                    description="Where the year stands, and what needs a look."
                />

                <AlertsPanel alerts={alerts} />

                {/* DASH-02: six cards, in this order, and no more (UX-09). */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <MetricCard
                        label="Available now"
                        value={formatMoney(currentAvailableCents)}
                        href={cashFlow({ year })}
                        tone={currentAvailableCents < 0 ? 'bad' : 'neutral'}
                        testId="card-available"
                    />

                    <MetricCard
                        label="Forecast year end"
                        value={formatMoney(yearEnd.forecastCents)}
                        href={forecast({ year })}
                        tone={yearEnd.forecastCents < 0 ? 'bad' : 'neutral'}
                        testId="card-year-end"
                    >
                        <MetricRows
                            rows={[
                                {
                                    label: 'Originally planned',
                                    value: yearEnd.hasBaseline
                                        ? formatMoney(yearEnd.plannedCents)
                                        : '—',
                                },
                                {
                                    label: 'Difference',
                                    value: yearEnd.hasBaseline
                                        ? formatMoney(yearEnd.deviationCents)
                                        : 'No baseline yet',
                                },
                            ]}
                        />
                    </MetricCard>

                    <MetricCard
                        label="Income so far"
                        value={formatMoney(income.actualCents)}
                        href={comparison({ year })}
                        testId="card-income"
                    >
                        <MetricRows
                            rows={[
                                {
                                    label: 'Planned',
                                    value: formatMoney(income.plannedCents),
                                },
                                {
                                    label: 'Forecast',
                                    value: formatMoney(income.forecastCents),
                                },
                            ]}
                        />
                    </MetricCard>

                    <MetricCard
                        label="Expenses so far"
                        value={formatMoney(expenses.actualCents)}
                        href={comparison({ year })}
                        testId="card-expenses"
                    >
                        <MetricRows
                            rows={[
                                {
                                    label: 'Planned',
                                    value: formatMoney(expenses.plannedCents),
                                },
                                {
                                    label: 'Forecast',
                                    value: formatMoney(expenses.forecastCents),
                                },
                            ]}
                        />
                    </MetricCard>

                    <MetricCard
                        label="Saved so far"
                        value={formatMoney(savings.actualCents)}
                        href={comparison({ year })}
                        tone={savings.actualCents < 0 ? 'bad' : 'neutral'}
                        testId="card-savings"
                    >
                        <MetricRows
                            rows={[
                                {
                                    label: 'Planned',
                                    value: formatMoney(savings.plannedCents),
                                },
                                {
                                    label: 'Savings rate',
                                    value: rate(
                                        savings.actualCents,
                                        savings.actualIncomeCents,
                                    ),
                                },
                            ]}
                        />
                    </MetricCard>

                    <MetricCard
                        label="Emergency fund"
                        value={formatMoney(fund.currentCents)}
                        href={emergencyFund()}
                        tone={fund.isReached ? 'good' : 'neutral'}
                        testId="card-emergency-fund"
                    >
                        <ProgressBar
                            current={fund.currentCents}
                            target={fund.targetCents}
                            label="Emergency fund progress"
                        />

                        <MetricRows
                            rows={[
                                {
                                    label: 'Target',
                                    value: formatMoney(fund.targetCents),
                                },
                                {
                                    label: fund.isReached
                                        ? 'Status'
                                        : 'Months to go',
                                    value: fund.isReached
                                        ? 'Fully funded'
                                        : (fund.monthsToTarget?.toString() ??
                                          'Not on this plan'),
                                },
                            ]}
                        />
                    </MetricCard>
                </div>

                {/* DASH-03: the compact row, for figures worth a glance but not a card. */}
                <div className="flex flex-wrap gap-3">
                    <SecondaryLink
                        href={netWorth()}
                        label="Net worth"
                        value={formatMoney(worth.currentCents)}
                        testId="secondary-net-worth"
                    />

                    <SecondaryLink
                        href={months({ year })}
                        label="Months finished"
                        value={`${completedMonths} / 12`}
                        testId="secondary-months"
                    />

                    {transactionIssues > 0 && (
                        <SecondaryLink
                            href={transactions.url({
                                query: { year, issues: 1 },
                            })}
                            label="Need a look"
                            value={
                                transactionIssues === 1
                                    ? '1 transaction'
                                    : `${transactionIssues} transactions`
                            }
                            testId="secondary-issues"
                        />
                    )}
                </div>

                {/* DASH-04: each chart arrives on its own, behind its own skeleton. */}
                <div className="grid gap-4 xl:grid-cols-2">
                    <Deferred
                        data="closingBalance"
                        fallback={<ChartSkeleton title="Closing balance" />}
                    >
                        <ClosingBalanceChart
                            points={closingBalance ?? []}
                            year={year}
                        />
                    </Deferred>

                    <Deferred
                        data="expensesByCategory"
                        fallback={
                            <ChartSkeleton title="Where the money went" />
                        }
                    >
                        <ExpensesByCategoryChart
                            data={expensesByCategory ?? { month: 1, rows: [] }}
                            monthLabel={monthName(
                                expensesByCategory?.month ?? 1,
                                formatLocale,
                            )}
                        />
                    </Deferred>

                    <Deferred
                        data="incomeAndExpenses"
                        fallback={<ChartSkeleton title="Month by month" />}
                    >
                        <IncomeExpenseChart
                            points={incomeAndExpenses ?? []}
                            year={year}
                        />
                    </Deferred>

                    <Deferred
                        data="netWorthTrend"
                        fallback={<ChartSkeleton title="Net worth" />}
                    >
                        <NetWorthTrendChart
                            points={netWorthTrend ?? []}
                            year={year}
                        />
                    </Deferred>

                    <Deferred
                        data="goalsProgress"
                        fallback={<ChartSkeleton title="Goals" />}
                    >
                        <GoalsProgressChart goals={goalsProgress ?? []} />
                    </Deferred>
                </div>
            </div>
        </AppLayout>
    );
}

/**
 * "March" — the month the expenses chart is showing, written out because it is read as a
 * sentence rather than an axis label.
 */
function monthName(month: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, { month: 'long' }).format(
        new Date(2000, month - 1, 1),
    );
}

function SecondaryLink({
    href,
    label,
    value,
    testId,
}: {
    href: NonNullable<InertiaLinkProps['href']>;
    label: string;
    value: string;
    testId: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-baseline gap-2 rounded-md border px-3 py-2 text-sm transition-colors hover:border-foreground/20"
            data-testid={testId}
        >
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">{value}</span>
        </Link>
    );
}
