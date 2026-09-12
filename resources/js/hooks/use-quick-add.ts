import { createContext, useContext } from 'react';

export type QuickAddContextValue = {
    isOpen: boolean;
    /** When the sheet was opened, so the save can report how long entry took (TXQ-10). */
    openedAt: number | null;
    open: () => void;
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
