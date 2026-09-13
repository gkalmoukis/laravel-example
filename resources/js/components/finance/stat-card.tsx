import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Tone = 'neutral' | 'plan' | 'actual' | 'forecast' | 'good' | 'bad';

/**
 * What a tone looks like, in one place, so the dashboard's linked cards and the figure
 * grids on every report cannot drift apart (§5.2).
 */
export const toneClass: Record<Tone, string> = {
    neutral: '',
    plan: 'text-series-plan',
    actual: 'text-series-actual',
    forecast: 'text-series-forecast',
    good: 'text-status-ok',
    bad: 'text-status-over',
};

/**
 * A row of headline figures.
 *
 * Cash flow, the forecast, the month review and net worth each wrote their own bordered
 * `<dl>` of the same shape, which is why the same figure sat at a different size on each
 * of them. One definition, so a figure looks like a figure everywhere (UX-05).
 */
export function StatGrid({
    columns = 4,
    className,
    children,
}: {
    columns?: 2 | 3 | 4;
    className?: string;
    children: ReactNode;
}) {
    return (
        <dl
            className={cn(
                'grid gap-4 rounded-lg border bg-card p-4 shadow-card',
                columns === 2 && 'sm:grid-cols-2',
                columns === 3 && 'sm:grid-cols-3',
                columns === 4 && 'sm:grid-cols-4',
                className,
            )}
        >
            {children}
        </dl>
    );
}

/**
 * One figure, with an optional second line for what it is being measured against.
 *
 * `tone` carries the §5.2 language — plan, actual, forecast — and never carries meaning
 * on its own: the label above always says which is which.
 */
export function Stat({
    label,
    value,
    tone = 'neutral',
    sub,
    testId,
}: {
    label: ReactNode;
    value: string;
    tone?: Tone;
    sub?: ReactNode;
    testId?: string;
}) {
    return (
        <div data-testid={testId}>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'mt-1 font-display text-xl font-normal tabular-nums',
                    toneClass[tone],
                )}
            >
                {value}
            </dd>
            {sub && (
                <dd className="text-xs text-muted-foreground tabular-nums">
                    {sub}
                </dd>
            )}
        </div>
    );
}
