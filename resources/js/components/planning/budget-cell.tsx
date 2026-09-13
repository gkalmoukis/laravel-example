import { router } from '@inertiajs/react';
import { Check, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import BudgetCellController from '@/actions/App/Http/Controllers/BudgetCellController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { usePreferences } from '@/hooks/use-preferences';
import { cn } from '@/lib/utils';

type Written = {
    categoryId: number;
    month: number;
    previous: string;
    label: string;
};

export type BudgetGrid = {
    pendingKey: string | null;
    savedKey: string | null;
    failure: { key: string; message: string } | null;
    lastWritten: Written | null;
    save: (written: Written, amount: string) => void;
    undo: () => void;
    dismissUndo: () => void;
};

export function cellKey(categoryId: number, month: number): string {
    return `${categoryId}-${month}`;
}

/**
 * A handle a test can name without knowing a database id — "Housing, Mar" becomes
 * `cell-housing-mar`. The browser locator resolves `#id` and `[name]` but not
 * `aria-label`, so the accessible name alone is not something a test can reach.
 */
export function cellTestId(label: string): string {
    return `cell-${label
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '')}`;
}

/**
 * What the grid is doing, for the whole grid at once.
 *
 * Every cell used to PATCH on blur and say nothing at all: no indication it had saved,
 * no sign when the server refused, and no way back from a number typed into the wrong
 * row. The state lives up here because only one cell is ever in flight — the write is
 * triggered by leaving it (BUD-02, BUD-03).
 */
export function useBudgetGrid(year: number): BudgetGrid {
    const [pendingKey, setPendingKey] = useState<string | null>(null);
    const [savedKey, setSavedKey] = useState<string | null>(null);
    const [failure, setFailure] = useState<{
        key: string;
        message: string;
    } | null>(null);
    const [lastWritten, setLastWritten] = useState<Written | null>(null);

    const write = (written: Written, amount: string, remember: boolean) => {
        const key = cellKey(written.categoryId, written.month);

        setPendingKey(key);
        setSavedKey(null);
        setFailure(null);

        router.patch(
            BudgetCellController.update.url({ year }),
            {
                category_id: written.categoryId,
                month: written.month,
                amount,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSavedKey(key);
                    setLastWritten(remember ? written : null);
                },
                // The refusal the controller already returns — an amount that will not
                // parse, or a category with more than one planned item behind it, which
                // the grid cannot attribute a number to.
                onError: (errors) =>
                    setFailure({
                        key,
                        message: errors.amount ?? 'That could not be saved.',
                    }),
                onFinish: () => setPendingKey(null),
            },
        );
    };

    return {
        pendingKey,
        savedKey,
        failure,
        lastWritten,
        save: (written, amount) => write(written, amount, true),
        undo: () => {
            if (lastWritten !== null) {
                write(lastWritten, lastWritten.previous, false);
            }
        },
        dismissUndo: () => setLastWritten(null),
    };
}

/**
 * One editable amount.
 *
 * Up and down move between rows; left and right are left alone so they still move the
 * caret inside the number the user is typing.
 */
export function BudgetCell({
    grid,
    categoryId,
    month,
    label,
    cents,
    className,
    onMove,
}: {
    grid: BudgetGrid;
    categoryId: number;
    month: number;
    label: string;
    cents: number;
    className?: string;
    onMove?: (direction: -1 | 1) => void;
}) {
    const { formatAmount } = usePreferences();

    const initial = formatAmount(cents);
    const [value, setValue] = useState(initial);

    const key = cellKey(categoryId, month);
    const failed = grid.failure?.key === key;

    const commit = () => {
        // Nothing changed, so nothing to say and nothing to undo.
        if (value === initial) {
            return;
        }

        grid.save({ categoryId, month, previous: initial, label }, value);
    };

    return (
        <div className="flex items-center gap-1">
            <Input
                id={`budget-cell-${key}`}
                data-testid={cellTestId(label)}
                aria-label={label}
                aria-invalid={failed}
                inputMode="decimal"
                value={value}
                className={cn('text-right tabular-nums', className)}
                onChange={(event) => setValue(event.target.value)}
                onBlur={commit}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        commit();
                        onMove?.(1);

                        return;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        commit();
                        onMove?.(-1);

                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        commit();
                        onMove?.(1);
                    }
                }}
            />

            <CellStatus grid={grid} cellId={key} />
        </div>
    );
}

/**
 * Colour is never the only signal, so each state carries its own icon and a word for a
 * screen reader (§5.2, NFR-06).
 */
function CellStatus({ grid, cellId }: { grid: BudgetGrid; cellId: string }) {
    if (grid.pendingKey === cellId) {
        return <Spinner className="size-3 shrink-0 text-muted-foreground" />;
    }

    if (grid.failure?.key === cellId) {
        return (
            <span
                className="shrink-0 text-status-over"
                data-testid={`cell-error-${cellId}`}
            >
                <TriangleAlert className="size-3" aria-hidden="true" />
                <span className="sr-only">Not saved</span>
            </span>
        );
    }

    if (grid.savedKey === cellId) {
        return (
            <span
                className="shrink-0 text-status-ok"
                data-testid={`cell-saved-${cellId}`}
            >
                <Check className="size-3" aria-hidden="true" />
                <span className="sr-only">Saved</span>
            </span>
        );
    }

    return <span className="size-3 shrink-0" aria-hidden="true" />;
}

/**
 * The one line that says what just happened, and offers it back.
 */
export function BudgetGridStatus({ grid }: { grid: BudgetGrid }) {
    if (grid.failure !== null) {
        return (
            <p
                className="flex items-center gap-2 text-sm text-status-over"
                data-testid="budget-error"
            >
                <TriangleAlert className="size-4" aria-hidden="true" />
                {grid.failure.message}
            </p>
        );
    }

    if (grid.lastWritten === null) {
        return null;
    }

    return (
        <p
            className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
            data-testid="budget-undo"
        >
            Saved {grid.lastWritten.label}.
            <Button
                variant="outline"
                size="sm"
                onClick={grid.undo}
                data-testid="undo-budget-cell"
            >
                Undo
            </Button>
        </p>
    );
}
