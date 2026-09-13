import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePreferences } from '@/hooks/use-preferences';
import { destroy as destroyTransaction } from '@/routes/transactions';
import type { TransactionRow } from '@/types/transactions';

/**
 * Deleting is permanent, so the question names the transaction rather than asking "are you
 * sure?" about nothing in particular (TXF-03).
 */
export default function DeleteTransactionDialog({
    transaction,
    onClose,
}: {
    transaction: TransactionRow;
    onClose: () => void;
}) {
    const { formatMoney, formatDayAndMonth, formatLocale } = usePreferences();
    const form = useForm<{ reopen_month: boolean }>({ reopen_month: false });

    const blockedMonth = Boolean(form.errors.reopen_month);

    const monthName = new Intl.DateTimeFormat(formatLocale, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${transaction.occurredOn}T00:00:00`));

    const remove = (reopen: boolean) => {
        form.transform(() => ({ reopen_month: reopen }));
        form.delete(destroyTransaction.url(transaction.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Delete this {formatMoney(transaction.amountCents)}{' '}
                        {transaction.type === 'income' ? 'income' : 'expense'}{' '}
                        from {formatDayAndMonth(transaction.occurredOn)}?
                    </DialogTitle>
                    <DialogDescription>
                        {transaction.description} · {transaction.categoryName}.
                        This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                {blockedMonth && (
                    <p
                        className="text-sm text-status-warning"
                        data-testid="delete-month-complete"
                    >
                        {monthName} is marked complete. Reopen it to delete
                        this.
                    </p>
                )}

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Keep it
                    </Button>

                    {blockedMonth ? (
                        <Button
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() => remove(true)}
                            data-testid="reopen-and-delete"
                        >
                            Reopen the month and delete
                        </Button>
                    ) : (
                        <Button
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() => remove(false)}
                            data-testid="confirm-delete"
                        >
                            Delete
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
