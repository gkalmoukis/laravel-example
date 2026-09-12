import { CalendarClock } from 'lucide-react';
import IssueBadge from '@/components/transactions/issue-badge';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { usePreferences } from '@/hooks/use-preferences';
import { cn } from '@/lib/utils';
import type { TransactionRow as Row } from '@/types/transactions';

function isFuture(occurredOn: string, today: string): boolean {
    return occurredOn > today;
}

/**
 * Income and expense are told apart by a sign as well as a colour, so the distinction
 * survives a colour-blind reader and a black-and-white print (FE-14, TXL-03).
 */
function Amount({ row }: { row: Row }) {
    const { formatMoney } = usePreferences();
    const isIncome = row.type === 'income';

    return (
        <span
            className={cn(
                'font-medium tabular-nums',
                isIncome
                    ? 'text-emerald-700 dark:text-emerald-400'
                    : 'text-foreground',
            )}
        >
            {isIncome ? '+' : '−'} {formatMoney(row.amountCents)}
        </span>
    );
}

function Meta({ row, today }: { row: Row; today: string }) {
    return (
        <>
            {isFuture(row.occurredOn, today) && (
                <Badge variant="secondary" className="gap-1">
                    <CalendarClock className="size-3" aria-hidden="true" />
                    Future date
                </Badge>
            )}
            <IssueBadge issues={row.issues} />
        </>
    );
}

export function TransactionTableRow({
    row,
    today,
}: {
    row: Row;
    today: string;
}) {
    const { formatDate } = usePreferences();

    return (
        <TableRow data-testid="transaction-row">
            <TableCell className="whitespace-nowrap tabular-nums">
                {formatDate(row.occurredOn)}
            </TableCell>
            <TableCell>
                <div className="flex flex-wrap items-center gap-2">
                    <span>{row.description}</span>
                    <Meta row={row} today={today} />
                </div>
            </TableCell>
            <TableCell className="text-muted-foreground">
                {row.categoryName}
                {row.subcategoryName ? ` › ${row.subcategoryName}` : ''}
            </TableCell>
            <TableCell className="text-muted-foreground">
                {row.accountName ?? '—'}
            </TableCell>
            <TableCell className="text-right">
                <Amount row={row} />
            </TableCell>
        </TableRow>
    );
}

/**
 * The same row on a phone. Five columns do not fit at 375 px, so the row becomes a card
 * rather than something the user has to scroll sideways through (UX-13).
 */
export function TransactionCard({ row, today }: { row: Row; today: string }) {
    const { formatDate } = usePreferences();

    return (
        <div className="rounded-lg border p-3" data-testid="transaction-card">
            <div className="flex items-start justify-between gap-3">
                <span className="font-medium">{row.description}</span>
                <Amount row={row} />
            </div>

            <div className="mt-1 text-sm text-muted-foreground">
                {formatDate(row.occurredOn)} · {row.categoryName}
                {row.subcategoryName ? ` › ${row.subcategoryName}` : ''}
                {row.accountName ? ` · ${row.accountName}` : ''}
            </div>

            <div className="mt-2 flex flex-wrap gap-2 empty:mt-0">
                <Meta row={row} today={today} />
            </div>
        </div>
    );
}
