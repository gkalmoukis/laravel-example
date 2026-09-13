import { Head, Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/finance/empty-state';
import Heading from '@/components/heading';
import BulkActionsBar from '@/components/transactions/bulk-actions-bar';
import TransactionFilters from '@/components/transactions/transaction-filters';
import {
    TransactionCard,
    TransactionTableRow,
} from '@/components/transactions/transaction-row';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePreferences } from '@/hooks/use-preferences';
import AppLayout from '@/layouts/app-layout';
import { index as transactionsIndex } from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';
import type {
    PaginationState,
    TransactionFilters as Filters,
    TransactionOptions,
    TransactionRow,
    TransactionTotals,
} from '@/types/transactions';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: transactionsIndex() },
];

function Totals({ totals }: { totals: TransactionTotals }) {
    const { formatMoney } = usePreferences();

    return (
        <dl
            className="grid grid-cols-3 gap-4 rounded-lg border p-4 text-sm"
            data-testid="list-totals"
        >
            <div>
                <dt className="text-muted-foreground">Income</dt>
                <dd className="mt-1 font-medium tabular-nums">
                    {formatMoney(totals.incomeCents)}
                </dd>
            </div>
            <div>
                <dt className="text-muted-foreground">Expenses</dt>
                <dd className="mt-1 font-medium tabular-nums">
                    {formatMoney(totals.expenseCents)}
                </dd>
            </div>
            <div>
                <dt className="text-muted-foreground">Net</dt>
                <dd className="mt-1 font-medium tabular-nums">
                    {formatMoney(totals.netCents)}
                </dd>
            </div>
        </dl>
    );
}

function Pager({
    pagination,
    filters,
}: {
    pagination: PaginationState;
    filters: Filters;
}) {
    if (pagination.lastPage <= 1) {
        return null;
    }

    const query = (page: number) => {
        const params: Record<string, string> = { page: String(page) };

        if (filters.q) {
            params.q = filters.q;
        }

        if (filters.type) {
            params.type = filters.type;
        }

        if (filters.categoryId) {
            params.category_id = String(filters.categoryId);
        }

        if (filters.onlyIssues) {
            params.issues = '1';
        }

        return transactionsIndex.url({ query: params });
    };

    const hasPrevious = pagination.currentPage > 1;
    const hasNext = pagination.currentPage < pagination.lastPage;

    return (
        <nav
            className="flex items-center justify-between gap-2"
            aria-label="Pagination"
        >
            {hasPrevious ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={query(pagination.currentPage - 1)}>
                        Previous
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" size="sm" disabled>
                    Previous
                </Button>
            )}

            <span className="text-sm text-muted-foreground">
                Page {pagination.currentPage} of {pagination.lastPage} ·{' '}
                {pagination.total} transactions
            </span>

            {hasNext ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={query(pagination.currentPage + 1)}>Next</Link>
                </Button>
            ) : (
                <Button variant="outline" size="sm" disabled>
                    Next
                </Button>
            )}
        </nav>
    );
}

export default function TransactionsIndex({
    transactions,
    pagination,
    totals,
    filters,
    options,
}: {
    transactions: TransactionRow[];
    pagination: PaginationState;
    totals: TransactionTotals;
    filters: Filters;
    options: TransactionOptions;
}) {
    const { today } = usePreferences();
    const todayIso = today();

    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    const toggle = (id: number, checked: boolean) => {
        setSelectedIds((current) =>
            checked
                ? [...current, id]
                : current.filter((selected) => selected !== id),
        );
    };

    // "Select all" means all on this page, not every row the filter matches — selecting
    // rows the user cannot see would make the count meaningless (TXL-04).
    const pageIds = transactions.map((row) => row.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transactions" />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title="Transactions"
                    description="Everything you have recorded, newest first."
                />

                <TransactionFilters filters={filters} options={options} />

                <BulkActionsBar
                    selectedIds={selectedIds}
                    options={options}
                    onDone={() => setSelectedIds([])}
                />

                {transactions.length === 0 ? (
                    <EmptyState
                        title="Nothing matches these filters yet"
                        message="Widen the dates, or clear them and start again."
                        icon={Receipt}
                        actionLabel="Clear the filters"
                        actionHref={transactionsIndex()}
                    />
                ) : (
                    <>
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={allSelected}
                                                onCheckedChange={(checked) =>
                                                    setSelectedIds(
                                                        checked === true
                                                            ? pageIds
                                                            : [],
                                                    )
                                                }
                                                aria-label="Select all on this page"
                                                data-testid="select-all"
                                            />
                                        </TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Account</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead className="w-10">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.map((row) => (
                                        <TransactionTableRow
                                            key={row.id}
                                            row={row}
                                            today={todayIso}
                                            selected={selectedIds.includes(
                                                row.id,
                                            )}
                                            onSelect={(checked) =>
                                                toggle(row.id, checked)
                                            }
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-2 md:hidden">
                            {/*
                             * Select-all lives in the table header on a desktop, which is
                             * hidden here — so the card list carries its own, or bulk
                             * refiling would be desktop-only (TXL-04, NFR-06).
                             */}
                            <label className="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                <Checkbox
                                    checked={allSelected}
                                    onCheckedChange={(checked) =>
                                        setSelectedIds(
                                            checked === true ? pageIds : [],
                                        )
                                    }
                                    aria-label="Select all on this page"
                                    data-testid="select-all-cards"
                                />
                                Select all on this page
                            </label>

                            {transactions.map((row) => (
                                <TransactionCard
                                    key={row.id}
                                    row={row}
                                    today={todayIso}
                                    selected={selectedIds.includes(row.id)}
                                    onSelect={(checked) =>
                                        toggle(row.id, checked)
                                    }
                                />
                            ))}
                        </div>

                        <Totals totals={totals} />

                        <Pager pagination={pagination} filters={filters} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
