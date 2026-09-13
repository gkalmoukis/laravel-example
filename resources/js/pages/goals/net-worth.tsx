import { Link, router, useForm } from '@inertiajs/react';
import { Plus, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';
import ChartDataTable from '@/components/finance/chart-data-table';
import GlossaryTerm from '@/components/finance/glossary-term';
import { Stat } from '@/components/finance/stat-card';
import InputError from '@/components/input-error';
import NetWorthChart, {
    type NetWorthPoint,
} from '@/components/net-worth/net-worth-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePreferences } from '@/hooks/use-preferences';
import GoalsLayout from '@/pages/goals/layout';
import { create as createYear } from '@/routes/financial-years';
import {
    destroy as retireItem,
    store as storeItem,
} from '@/routes/net-worth-items';

type Holding = {
    itemId: number;
    name: string;
    kind: string;
    valueCents: number;
    isCarriedForward: boolean;
};

type MonthPosition = {
    month: number;
    assetsCents: number;
    debtsCents: number;
    netCents: number;
    byKind: Record<string, number>;
    holdings: Holding[];
};

type Item = { id: number; name: string; kind: string; isActive: boolean };

export default function NetWorthIndex({
    hasYear,
    year,
    current,
    changeVsPreviousMonth,
    changeVsStartOfYear,
    months,
    items,
    kinds,
}: {
    hasYear: boolean;
    year: number | null;
    current: MonthPosition | null;
    changeVsPreviousMonth: number | null;
    changeVsStartOfYear: number | null;
    months: MonthPosition[];
    items: Item[];
    kinds: { value: string; label: string }[];
}) {
    const { formatMoney, formatAmount, formatLocale } = usePreferences();
    const [adding, setAdding] = useState(false);

    const form = useForm({ name: '', kind: 'cash' });

    const label = (month: number) =>
        month === 0
            ? 'Start'
            : new Intl.DateTimeFormat(formatLocale, { month: 'short' }).format(
                  new Date(2000, month - 1, 1),
              );

    const kindLabel = (value: string) =>
        kinds.find((kind) => kind.value === value)?.label ?? value;

    const points: NetWorthPoint[] = months.map((month) => ({
        label: label(month.month),
        net: month.netCents,
    }));

    const tableRows = months.map((month) => [
        label(month.month),
        formatAmount(month.assetsCents),
        formatAmount(month.debtsCents),
        formatAmount(month.netCents),
    ]);

    const Change = ({ value, label }: { value: number; label: string }) => (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="mt-1 flex items-center gap-1 font-medium tabular-nums">
                {value >= 0 ? (
                    <TrendingUp
                        className="size-4 text-status-ok"
                        aria-hidden="true"
                    />
                ) : (
                    <TrendingDown
                        className="size-4 text-status-over"
                        aria-hidden="true"
                    />
                )}
                {formatMoney(value)}
            </dd>
        </div>
    );

    return (
        <GoalsLayout
            tab="net-worth"
            title="Net worth"
            description={
                <>
                    Your <GlossaryTerm term="netWorth">net worth</GlossaryTerm>,
                    month by month.
                </>
            }
        >
            <div className="space-y-6">
                {!hasYear || !current ? (
                    <div
                        className="rounded-lg border border-dashed p-10 text-center"
                        data-testid="needs-year"
                    >
                        <p className="text-muted-foreground">
                            Net worth is tracked against a year, so there is
                            nothing to show yet.
                        </p>
                        <Button className="mt-3" asChild>
                            <Link href={createYear()}>Create a plan</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Where you stand
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p
                                    className="text-3xl font-semibold tabular-nums"
                                    data-testid="current-net-worth"
                                >
                                    {formatMoney(current.netCents)}
                                </p>

                                <dl className="grid gap-4 sm:grid-cols-4">
                                    {changeVsPreviousMonth !== null && (
                                        <Change
                                            value={changeVsPreviousMonth}
                                            label="Since last month"
                                        />
                                    )}
                                    {changeVsStartOfYear !== null && (
                                        <Change
                                            value={changeVsStartOfYear}
                                            label={`Since ${year} began`}
                                        />
                                    )}
                                    <Stat
                                        label="Owned"
                                        value={formatMoney(current.assetsCents)}
                                    />
                                    <Stat
                                        label="Owed"
                                        value={formatMoney(current.debtsCents)}
                                    />
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Over {year}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ChartDataTable
                                    caption={`Net worth at the end of each month of ${year}`}
                                    columns={['Month', 'Owned', 'Owed', 'Net']}
                                    rows={tableRows}
                                >
                                    <NetWorthChart points={points} />
                                </ChartDataTable>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    What it is made of
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="divide-y">
                                    {current.holdings.map((holding) => (
                                        <li
                                            key={holding.itemId}
                                            className="flex items-center justify-between gap-2 py-2"
                                            data-testid={`holding-${holding.itemId}`}
                                        >
                                            <span>
                                                {holding.name}
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {kindLabel(holding.kind)}
                                                </span>
                                            </span>

                                            <span className="flex items-center gap-2">
                                                {holding.isCarriedForward && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge
                                                                variant="outline"
                                                                className="text-muted-foreground"
                                                                data-testid={`carried-${holding.itemId}`}
                                                            >
                                                                carried forward
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            You have not given a
                                                            newer figure, so the
                                                            last one you gave is
                                                            still being used.
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}

                                                <span
                                                    className={`tabular-nums ${
                                                        holding.isCarriedForward
                                                            ? 'text-muted-foreground'
                                                            : ''
                                                    }`}
                                                >
                                                    {formatMoney(
                                                        holding.valueCents,
                                                    )}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">
                            What you own and owe
                        </CardTitle>
                        <Button
                            size="sm"
                            onClick={() => setAdding(!adding)}
                            data-testid="add-holding"
                        >
                            <Plus className="size-4" />
                            Add
                        </Button>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {adding && (
                            <div className="grid gap-3 rounded-md border p-3 sm:grid-cols-3">
                                <div>
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        className="mt-1"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div>
                                    <Label htmlFor="kind">Kind</Label>
                                    <Select
                                        value={form.data.kind}
                                        onValueChange={(value) =>
                                            form.setData('kind', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="kind"
                                            className="mt-1 w-full"
                                            data-testid="holding-kind"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {kinds.map((kind) => (
                                                <SelectItem
                                                    key={kind.value}
                                                    value={kind.value}
                                                >
                                                    {kind.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex items-end">
                                    <Button
                                        disabled={form.processing}
                                        onClick={() =>
                                            form.post(storeItem.url(), {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    form.reset();
                                                    setAdding(false);
                                                },
                                            })
                                        }
                                        data-testid="save-holding"
                                    >
                                        Add it
                                    </Button>
                                </div>
                            </div>
                        )}

                        <ul className="divide-y">
                            {items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-center justify-between gap-2 py-2 text-sm"
                                >
                                    <span
                                        className={
                                            item.isActive
                                                ? ''
                                                : 'text-muted-foreground line-through'
                                        }
                                    >
                                        {item.name}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {kindLabel(item.kind)}
                                        </span>
                                    </span>

                                    {item.isActive && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    retireItem.url(item.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                            data-testid={`retire-${item.id}`}
                                        >
                                            No longer have it
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {items.length === 0 && (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Wallet className="size-4" aria-hidden="true" />
                                Nothing recorded yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </GoalsLayout>
    );
}
