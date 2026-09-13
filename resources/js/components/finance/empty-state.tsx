import { type InertiaLinkProps, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/**
 * What a list or a chart shows when it has nothing in it (EDGE-01).
 *
 * Always one way forward and never more than one: a screen that says "nothing here" and
 * stops leaves the user to work out what they were supposed to do, and a screen offering
 * three choices has turned an empty list into a decision.
 *
 * The action is optional only where there is genuinely nothing to offer — a filtered list
 * with no matches is answered by changing the filter, not by adding a record.
 */
export default function EmptyState({
    title,
    message,
    actionLabel,
    actionHref,
    onAction,
    icon: Icon = Inbox,
    testId,
    children,
}: {
    title: string;
    message?: string;
    actionLabel?: string;
    actionHref?: NonNullable<InertiaLinkProps['href']>;
    onAction?: () => void;
    icon?: LucideIcon;
    testId?: string;
    children?: ReactNode;
}) {
    return (
        <div
            className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-10 text-center"
            data-testid={testId ?? 'empty-state'}
        >
            <Icon className="size-8 text-muted-foreground" aria-hidden="true" />

            <div className="space-y-1">
                <p className="font-medium">{title}</p>

                {message !== undefined && (
                    <p className="text-sm text-muted-foreground">{message}</p>
                )}
            </div>

            {actionHref !== undefined && actionLabel !== undefined && (
                <Button variant="outline" size="sm" asChild>
                    <Link href={actionHref} data-testid="empty-state-action">
                        {actionLabel}
                    </Link>
                </Button>
            )}

            {onAction !== undefined && actionLabel !== undefined && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={onAction}
                    data-testid="empty-state-action"
                >
                    {actionLabel}
                </Button>
            )}

            {children}
        </div>
    );
}

/**
 * The same idea inside a list that already has its own border (EDGE-01).
 *
 * Four screens wrote their own one-line "nothing here" and none of them said what to do
 * next. A full `EmptyState` inside a bordered list would be a box within a box, so this
 * is the same promise in the shape a list row can take.
 */
export function EmptyRow({
    message,
    hint,
    testId,
}: {
    message: string;
    hint?: string;
    testId?: string;
}) {
    return (
        <li
            className="p-4 text-sm text-muted-foreground"
            data-testid={testId ?? 'empty-row'}
        >
            {message}
            {hint !== undefined && <span className="mt-1 block">{hint}</span>}
        </li>
    );
}
