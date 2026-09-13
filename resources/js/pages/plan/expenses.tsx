import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import BudgetCellController from '@/actions/App/Http/Controllers/BudgetCellController';
import Money from '@/components/planning/money';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePreferences } from '@/hooks/use-preferences';
import PlanLayout, { type PlanYear } from './layout';

type Row = {
    categoryId: number;
    categoryName: string;
    months: Record<number, number>;
    annualCents: number;
    isEditable: boolean;
    itemCount: number;
    doubleCounts: string[];
};

type Props = {
    year: PlanYear;
    tab: string;
    tabs: string[];
    rows: Row[];
    footerMonths: Record<number, number>;
    footerAnnualCents: number;
};

const monthLabels = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const months = Array.from({ length: 12 }, (_, index) => index + 1);

function save(year: number, categoryId: number, month: number, amount: string) {
    router.patch(
        BudgetCellController.update.url({ year }),
        { category_id: categoryId, month, amount },
        { preserveScroll: true },
    );
}

export default function PlanExpenses({
    year,
    tab,
    tabs,
    rows,
    footerMonths,
    footerAnnualCents,
}: Props) {
    // The grid is unreadable at phone width, so it becomes one month at a time
    // rather than scrolling sideways through twelve columns.
    const [month, setMonth] = useState(1);

    return (
        <PlanLayout
            year={year}
            tab={tab}
            tabs={tabs}
            title="Monthly budget"
            description="What you expect to spend, by category and month."
        >
            <div className="space-y-6">
                <div className="md:hidden">
                    <MonthlyView
                        year={year.year}
                        rows={rows}
                        month={month}
                        onMonthChange={setMonth}
                    />
                </div>

                <div className="hidden md:block">
                    <Grid
                        year={year.year}
                        rows={rows}
                        footerMonths={footerMonths}
                        footerAnnualCents={footerAnnualCents}
                    />
                </div>
            </div>
        </PlanLayout>
    );
}

function Grid({
    year,
    rows,
    footerMonths,
    footerAnnualCents,
}: {
    year: number;
    rows: Row[];
    footerMonths: Record<number, number>;
    footerAnnualCents: number;
}) {
    const { formatAmount } = usePreferences();

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="min-w-40">Category</TableHead>

                        {months.map((value) => (
                            <TableHead key={value} className="text-right">
                                {monthLabels[value - 1]}
                            </TableHead>
                        ))}

                        <TableHead className="text-right">Year</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.categoryId}>
                            <TableCell className="font-medium">
                                {row.categoryName}

                                <DoubleCountWarning row={row} />

                                {!row.isEditable && (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <span className="ml-1 cursor-help text-muted-foreground">
                                                *
                                            </span>
                                        </TooltipTrigger>

                                        <TooltipContent>
                                            {row.itemCount} planned items here,
                                            so this row shows their total. Edit
                                            them individually.
                                        </TooltipContent>
                                    </Tooltip>
                                )}
                            </TableCell>

                            {months.map((value) => (
                                <TableCell key={value} className="p-1">
                                    {row.isEditable ? (
                                        <Input
                                            aria-label={`${row.categoryName}, ${monthLabels[value - 1]}`}
                                            inputMode="decimal"
                                            defaultValue={formatAmount(
                                                row.months[value] ?? 0,
                                            )}
                                            className="h-8 w-24 text-right tabular-nums"
                                            onBlur={(event) =>
                                                save(
                                                    year,
                                                    row.categoryId,
                                                    value,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    ) : (
                                        <div className="px-2 text-right text-muted-foreground tabular-nums">
                                            {formatAmount(
                                                row.months[value] ?? 0,
                                            )}
                                        </div>
                                    )}
                                </TableCell>
                            ))}

                            <TableCell className="text-right font-medium">
                                <Money cents={row.annualCents} />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>

                <TableFooter>
                    <TableRow>
                        <TableCell className="font-medium">Total</TableCell>

                        {months.map((value) => (
                            <TableCell
                                key={value}
                                className="text-right font-medium tabular-nums"
                            >
                                {formatAmount(footerMonths[value] ?? 0)}
                            </TableCell>
                        ))}

                        <TableCell className="text-right font-medium">
                            <Money cents={footerAnnualCents} />
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    );
}

function MonthlyView({
    year,
    rows,
    month,
    onMonthChange,
}: {
    year: number;
    rows: Row[];
    month: number;
    onMonthChange: (month: number) => void;
}) {
    const { formatAmount } = usePreferences();

    const total = rows.reduce((sum, row) => sum + (row.months[month] ?? 0), 0);

    return (
        <div className="space-y-4">
            <Select
                value={String(month)}
                onValueChange={(value) => onMonthChange(Number(value))}
            >
                <SelectTrigger aria-label="Month">
                    <SelectValue />
                </SelectTrigger>

                <SelectContent>
                    {months.map((value) => (
                        <SelectItem key={value} value={String(value)}>
                            {monthLabels[value - 1]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <ul className="divide-y rounded-md border">
                {rows.map((row) => (
                    <li
                        key={row.categoryId}
                        className="flex items-center justify-between gap-3 p-3"
                    >
                        <span className="flex-1 text-sm font-medium">
                            {row.categoryName}

                            <DoubleCountWarning row={row} />
                        </span>

                        {row.isEditable ? (
                            <Input
                                aria-label={`${row.categoryName}, ${monthLabels[month - 1]}`}
                                inputMode="decimal"
                                defaultValue={formatAmount(
                                    row.months[month] ?? 0,
                                )}
                                className="h-9 w-28 text-right tabular-nums"
                                onBlur={(event) =>
                                    save(
                                        year,
                                        row.categoryId,
                                        month,
                                        event.target.value,
                                    )
                                }
                            />
                        ) : (
                            <span className="w-28 pr-3 text-right text-muted-foreground tabular-nums">
                                {formatAmount(row.months[month] ?? 0)}
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            <div className="flex items-center justify-between rounded-md border p-3">
                <span className="text-sm font-medium">
                    {monthLabels[month - 1]} total
                </span>

                <Money cents={total} className="font-medium" />
            </div>
        </div>
    );
}

/**
 * A subscription already plans itself, so a hand-written item of the same name in the
 * same category is very likely the same cost twice over (SUB-05).
 */
function DoubleCountWarning({ row }: { row: Row }) {
    if (row.doubleCounts.length === 0) {
        return null;
    }

    const message = row.doubleCounts
        .map((name) => `This may double-count ${name}`)
        .join('. ');

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className="ml-1 inline-flex cursor-help align-middle text-amber-600 dark:text-amber-500"
                    data-testid={`double-count-${row.categoryId}`}
                >
                    <TriangleAlert className="size-4" aria-hidden="true" />
                    <span className="sr-only">{message}</span>
                </span>
            </TooltipTrigger>

            <TooltipContent>{message}</TooltipContent>
        </Tooltip>
    );
}
