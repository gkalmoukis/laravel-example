import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useQuickAdd } from '@/hooks/use-quick-add';

/**
 * The desktop entry point, in the top bar of every authenticated page (UX-11).
 */
export function NewTransactionButton() {
    const { open } = useQuickAdd();

    return (
        <Button
            size="sm"
            onClick={open}
            className="hidden md:inline-flex"
            data-testid="new-transaction"
        >
            <Plus className="size-4" />
            New transaction
        </Button>
    );
}

/**
 * The same thing on a phone: a thumb-sized target in the bottom right, where a thumb
 * already is (UX-11).
 */
export function NewTransactionFab() {
    const { open } = useQuickAdd();

    return (
        <Button
            size="icon"
            onClick={open}
            className="fixed right-4 bottom-4 z-40 size-14 rounded-full shadow-lg md:hidden"
            data-testid="new-transaction-fab"
        >
            <Plus className="size-6" aria-hidden="true" />
            <span className="sr-only">New transaction</span>
        </Button>
    );
}
