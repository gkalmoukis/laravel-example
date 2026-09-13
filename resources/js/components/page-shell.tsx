import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

/**
 * The gutter and vertical rhythm every screen shares.
 *
 * Each page used to pick its own `px-4 py-6`, and about half of them also chose their own
 * `space-y-`, so the distance between two cards depended on which screen you were
 * looking at. One place decides it now, and the gutter opens up on wider viewports
 * instead of pinning a 1280px layout to a phone's margins (UX-10).
 *
 * `stacked` is off for the hub layouts, which sequence a heading, a tab row and a body
 * themselves and would otherwise be spaced twice.
 */
export default function PageShell({
    stacked = true,
    className,
    children,
}: PropsWithChildren<{ stacked?: boolean; className?: string }>) {
    return (
        <div
            className={cn(
                'px-4 py-6 md:px-6 lg:px-8',
                stacked && 'space-y-8',
                className,
            )}
        >
            {children}
        </div>
    );
}
