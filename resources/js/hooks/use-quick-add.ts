import { createContext, useContext } from 'react';
import type { TransactionRow } from '@/types/transactions';

/**
 * What the sheet is for this time.
 *
 * Editing and duplicating reuse the same form as a new entry (TXF-01, TXF-02): the fields
 * are identical, and a second form would be a second place for the rules to drift.
 */
export type QuickAddMode =
    | { kind: 'create' }
    | { kind: 'edit'; transaction: TransactionRow }
    | { kind: 'duplicate'; transaction: TransactionRow };

export type QuickAddContextValue = {
    mode: QuickAddMode | null;
    /** When the sheet was opened, so the save can report how long entry took (TXQ-10). */
    openedAt: number | null;
    open: () => void;
    edit: (transaction: TransactionRow) => void;
    duplicate: (transaction: TransactionRow) => void;
    close: () => void;
};

export const QuickAddContext = createContext<QuickAddContextValue | null>(null);

export function useQuickAdd(): QuickAddContextValue {
    const value = useContext(QuickAddContext);

    if (!value) {
        throw new Error('useQuickAdd must be used inside the app layout.');
    }

    return value;
}

/**
 * Whether a keystroke landed somewhere the user is writing.
 *
 * The `N` shortcut must not fire while the user is typing a description that happens to
 * contain the letter (TXQ-01).
 */
export function isTypingInto(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
    );
}
