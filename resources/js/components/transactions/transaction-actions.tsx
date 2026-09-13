import { Copy, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteTransactionDialog from '@/components/transactions/delete-transaction-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useQuickAdd } from '@/hooks/use-quick-add';
import type { TransactionRow } from '@/types/transactions';

/**
 * Correcting, repeating and removing one transaction (TXL-03).
 *
 * All three open the form the user already knows rather than a screen of their own, so
 * there is nothing new to learn and nowhere for the rules to diverge.
 */
export default function TransactionActions({ row }: { row: TransactionRow }) {
    const { edit, duplicate } = useQuickAdd();
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        data-testid={`row-actions-${row.id}`}
                    >
                        <MoreHorizontal className="size-4" aria-hidden="true" />
                        <span className="sr-only">
                            Actions for {row.description}
                        </span>
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        onSelect={() => edit(row)}
                        data-testid={`edit-${row.id}`}
                    >
                        <Pencil className="size-4" />
                        Edit
                    </DropdownMenuItem>

                    <DropdownMenuItem
                        onSelect={() => duplicate(row)}
                        data-testid={`duplicate-${row.id}`}
                    >
                        <Copy className="size-4" />
                        Duplicate
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setConfirmingDelete(true)}
                        data-testid={`delete-${row.id}`}
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            {confirmingDelete && (
                <DeleteTransactionDialog
                    transaction={row}
                    onClose={() => setConfirmingDelete(false)}
                />
            )}
        </>
    );
}
