import {
    type ReactNode,
    useCallback,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { NewTransactionFab } from '@/components/transactions/new-transaction-button';
import QuickAddSheet from '@/components/transactions/quick-add-sheet';
import { QuickAddContext, isTypingInto } from '@/hooks/use-quick-add';

/**
 * Holds quick add open for the whole app shell, so it can be reached from anywhere
 * without the page underneath navigating away (TXQ-01, UX-12).
 */
export default function QuickAddProvider({
    children,
}: {
    children: ReactNode;
}) {
    const [openedAt, setOpenedAt] = useState<number | null>(null);

    const open = useCallback(() => setOpenedAt(Date.now()), []);
    const close = useCallback(() => setOpenedAt(null), []);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key !== 'n' && event.key !== 'N') {
                return;
            }

            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (isTypingInto(event.target)) {
                return;
            }

            event.preventDefault();
            setOpenedAt((current) => current ?? Date.now());
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const value = useMemo(
        () => ({ isOpen: openedAt !== null, openedAt, open, close }),
        [openedAt, open, close],
    );

    return (
        <QuickAddContext value={value}>
            {children}
            <NewTransactionFab />
            <QuickAddSheet />
        </QuickAddContext>
    );
}
