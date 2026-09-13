import {
    type ReactNode,
    useCallback,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { NewTransactionFab } from '@/components/transactions/new-transaction-button';
import QuickAddSheet from '@/components/transactions/quick-add-sheet';
import {
    QuickAddContext,
    type QuickAddMode,
    isTypingInto,
} from '@/hooks/use-quick-add';
import type { TransactionRow } from '@/types/transactions';

type Session = { mode: QuickAddMode; openedAt: number; id: number };

/**
 * Holds the transaction form open for the whole app shell, so it can be reached from
 * anywhere without the page underneath navigating away (TXQ-01, UX-12).
 *
 * Each opening is its own session with its own key, so the form is built fresh from
 * whatever it is editing rather than carrying the last entry's values into the next.
 */
export default function QuickAddProvider({
    children,
}: {
    children: ReactNode;
}) {
    const [session, setSession] = useState<Session | null>(null);

    const start = useCallback((mode: QuickAddMode) => {
        setSession({ mode, openedAt: Date.now(), id: Date.now() });
    }, []);

    const open = useCallback(() => start({ kind: 'create' }), [start]);
    const edit = useCallback(
        (transaction: TransactionRow) => start({ kind: 'edit', transaction }),
        [start],
    );
    const duplicate = useCallback(
        (transaction: TransactionRow) =>
            start({ kind: 'duplicate', transaction }),
        [start],
    );
    const close = useCallback(() => setSession(null), []);

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
            setSession(
                (current) =>
                    current ?? {
                        mode: { kind: 'create' },
                        openedAt: Date.now(),
                        id: Date.now(),
                    },
            );
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const value = useMemo(
        () => ({
            mode: session?.mode ?? null,
            openedAt: session?.openedAt ?? null,
            open,
            edit,
            duplicate,
            close,
        }),
        [session, open, edit, duplicate, close],
    );

    return (
        <QuickAddContext value={value}>
            {children}
            <NewTransactionFab />
            {session && (
                <QuickAddSheet
                    key={session.id}
                    mode={session.mode}
                    openedAt={session.openedAt}
                />
            )}
        </QuickAddContext>
    );
}
