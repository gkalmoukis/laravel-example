import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import MonthStatusBadge from '@/components/finance/month-status-badge';
import VarianceRow, {
    type VarianceRowData,
} from '@/components/finance/variance-row';
import Heading from '@/components/heading';
import CompleteMonthCard from '@/components/months/complete-month-card';
import SnapshotForm, {
    type Holding,
} from '@/components/net-worth/snapshot-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePreferences } from '@/hooks/use-preferences';
import AppLayout from '@/layouts/app-layout';
import { index as monthsIndex, show as monthShow } from '@/routes/months';
import { index as transactionsIndex } from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';

type Totals = {
    incomeCents: number;
    expenseCents: number;
    netCents: number;
    plannedIncomeCents: number;
    plannedExpenseCents: number;
    plannedNetCents: number;
    openingCents: number;
    closingCents: number;
    hasActual: boolean;
};

type Issue = {
    id: number;
    occurredOn: string;
    description: string;
    amountCents: number;
    type: string;
    categoryName: string;
    reasons: string[];
};

function Figure({
    label,
    planned,
    actual,
}: {
    label: string;
    planned: number;
    actual: number;
}) {
    const { formatMoney } = usePreferences();

    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="mt-1 text-xl font-semibold text-series-actual tabular-nums">
                {formatMoney(actual)}
            </dd>
            <dd className="text-xs text-series-plan tabular-nums">
                {formatMoney(planned)} planned
            </dd>
        </div>
    );
}

export default function MonthShow({
    year,
    month,
    status,
    needsConfirmation,
    totals,
    holdings,
    liquidClosingCents,
    issues,
    income,
    expenses,
}: {
    year: number;
    month: number;
    status: string;
    needsConfirmation: boolean;
    totals: Totals;
    holdings: Holding[];
    liquidClosingCents: number;
    issues: Issue[];
    income: VarianceRowData[];
    expenses: VarianceRowData[];
}) {
    const { formatMoney, formatDate, formatLocale } = usePreferences();

    const name = new Intl.DateTimeFormat(formatLocale, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: `${year} months`, href: monthsIndex({ year }) },
        { title: name, href: monthShow({ year, month }) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${name} · Month review`} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Heading
                        title={name}
                        description="What this month came to, and anything still to put right."
                    />
                    <MonthStatusBadge status={status} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Totals</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Figure
                                label="Income"
                                actual={totals.incomeCents}
                                planned={totals.plannedIncomeCents}
                            />
                            <Figure
                                label="Expenses"
                                actual={totals.expenseCents}
                                planned={totals.plannedExpenseCents}
                            />
                            <Figure
                                label="Net"
                                actual={totals.netCents}
                                planned={totals.plannedNetCents}
                            />
                        </dl>

                        <p className="mt-4 text-sm text-muted-foreground">
                            Opened with {formatMoney(totals.openingCents)} and
                            closed with {formatMoney(totals.closingCents)}
                            {totals.hasActual
                                ? '.'
                                : ', both from the forecast — this month has not been reached yet.'}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Anything to fix
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {issues.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing is flagged in {name}.
                            </p>
                        ) : (
                            <ul className="space-y-3" data-testid="issue-list">
                                {issues.map((issue) => (
                                    <li
                                        key={issue.id}
                                        className="flex flex-wrap items-center gap-2 text-sm"
                                    >
                                        <AlertTriangle
                                            className="size-4 text-status-warning"
                                            aria-hidden="true"
                                        />
                                        <span className="font-medium">
                                            {issue.description}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDate(issue.occurredOn)} ·{' '}
                                            {formatMoney(issue.amountCents)}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {issue.reasons.join(' ')}
                                        </span>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            className="ml-auto"
                                            asChild
                                        >
                                            <Link
                                                href={transactionsIndex.url({
                                                    query: {
                                                        q: issue.description,
                                                        issues: '1',
                                                    },
                                                })}
                                            >
                                                Fix it
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Where it went against the plan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <section>
                            <h3 className="mb-1 text-sm font-medium">
                                Expenses
                            </h3>
                            {expenses.map((row) => (
                                <VarianceRow
                                    key={row.categoryId}
                                    row={row}
                                    year={year}
                                    month={month}
                                />
                            ))}
                        </section>

                        <section>
                            <h3 className="mb-1 text-sm font-medium">Income</h3>
                            {income.map((row) => (
                                <VarianceRow
                                    key={row.categoryId}
                                    row={row}
                                    year={year}
                                    month={month}
                                />
                            ))}
                        </section>
                    </CardContent>
                </Card>

                <SnapshotForm
                    year={year}
                    month={month}
                    monthName={name}
                    holdings={holdings}
                    liquidClosingCents={liquidClosingCents}
                />

                <CompleteMonthCard
                    year={year}
                    month={month}
                    monthName={name}
                    isComplete={status === 'complete'}
                    needsConfirmation={needsConfirmation}
                />
            </div>
        </AppLayout>
    );
}
