import { Link } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    ChevronDown,
    ChevronRight,
    Check,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { usePreferences } from '@/hooks/use-preferences';
import { cn } from '@/lib/utils';
import { index as transactionsIndex } from '@/routes/transactions';

export type VarianceRowData = {
    categoryId: number;
    categoryName: string;
    plannedCents: number;
    actualCents: number;
    varianceCents: number;
    status: string;
    label: string;
    isSpread: boolean;
    subcategories: {
        id: number | null;
        name: string;
        actualCents: number;
    }[];
};

/**
 * Status is never colour alone: each carries an icon and a plain-language label as well,
 * so a colour-blind reader loses nothing (§5.2, FE-14).
 */
const STATUS = {
    ok: { icon: Check, className: 'text-status-ok border-status-ok/40' },
    warning: {
        icon: AlertTriangle,
        className: 'text-status-warning border-status-warning/40',
    },
    over: {
        icon: AlertOctagon,
        className: 'text-status-over border-status-over/40',
    },
    no_plan: { icon: Check, className: 'text-muted-foreground' },
} as const;

export default function VarianceRow({
    row,
    year,
    month,
}: {
    row: VarianceRowData;
    year: number;
    month: number;
}) {
    const { formatMoney } = usePreferences();
    const [open, setOpen] = useState(false);

    const status = STATUS[row.status as keyof typeof STATUS] ?? STATUS.no_plan;
    const Icon = status.icon;
    const Chevron = open ? ChevronDown : ChevronRight;

    const filtered = transactionsIndex.url({
        query: {
            category_id: String(row.categoryId),
            month: String(month),
            year: String(year),
        },
    });

    return (
        <div
            className="border-b last:border-b-0"
            data-testid={`variance-${row.categoryId}`}
        >
            <div className="flex flex-wrap items-center gap-2 py-2">
                <button
                    type="button"
                    className="flex items-center gap-1 text-left font-medium"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                >
                    <Chevron
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {row.categoryName}
                </button>

                {row.isSpread && (
                    <Badge
                        variant="secondary"
                        title="Judged on the year so far, because the real payment lands in one month"
                    >
                        Set aside monthly
                    </Badge>
                )}

                <Badge
                    variant="outline"
                    className={cn('ml-auto gap-1', status.className)}
                >
                    <Icon className="size-3" aria-hidden="true" />
                    {row.label}
                </Badge>
            </div>

            <dl className="grid grid-cols-3 gap-2 pb-2 text-sm">
                <div>
                    <dt className="text-muted-foreground">Plan</dt>
                    <dd className="text-series-plan tabular-nums">
                        {formatMoney(row.plannedCents)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Actual</dt>
                    <dd className="text-series-actual tabular-nums">
                        {/* Actuals are read-only and always link to what produced them (CMP-02). */}
                        <Link
                            className="underline underline-offset-4"
                            href={filtered}
                        >
                            {formatMoney(row.actualCents)}
                        </Link>
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Difference</dt>
                    <dd className="tabular-nums">
                        {formatMoney(row.varianceCents)}
                    </dd>
                </div>
            </dl>

            {open && (
                <ul
                    className="pb-3 text-sm"
                    data-testid={`breakdown-${row.categoryId}`}
                >
                    {row.subcategories.length === 0 ? (
                        <li className="text-muted-foreground">
                            Nothing recorded in this category yet.
                        </li>
                    ) : (
                        row.subcategories.map((sub) => (
                            <li
                                key={sub.id ?? 'none'}
                                className="flex justify-between py-0.5"
                            >
                                <span className="text-muted-foreground">
                                    {sub.name}
                                </span>
                                <span className="tabular-nums">
                                    {formatMoney(sub.actualCents)}
                                </span>
                            </li>
                        ))
                    )}
                </ul>
            )}
        </div>
    );
}
