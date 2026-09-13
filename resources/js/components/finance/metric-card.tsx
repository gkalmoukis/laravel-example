import { type InertiaLinkProps, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * One figure on the dashboard, with somewhere to go and read the rest (DASH-06).
 *
 * The whole card is the link, so the target is a comfortable size on a phone rather than
 * a word at the bottom. The heading is a real heading, so the six cards can be skimmed
 * with a screen reader in the order the specification sets (NFR-06).
 */
export default function MetricCard({
    label,
    value,
    href,
    tone = 'neutral',
    testId,
    children,
}: {
    label: string;
    value: string;
    href: NonNullable<InertiaLinkProps['href']>;
    tone?: 'neutral' | 'good' | 'bad';
    testId: string;
    children?: ReactNode;
}) {
    return (
        <Card className="relative transition-colors hover:border-foreground/20">
            <CardContent className="space-y-2">
                <h2 className="flex items-center justify-between gap-2 text-sm font-medium text-muted-foreground">
                    <Link
                        href={href}
                        className="after:absolute after:inset-0 focus-visible:underline"
                        data-testid={testId}
                    >
                        {label}
                    </Link>

                    <ArrowRight
                        className="size-3 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </h2>

                <p
                    className={cn(
                        'font-display text-3xl font-normal tabular-nums',
                        tone === 'good' && 'text-status-ok',
                        tone === 'bad' && 'text-status-over',
                    )}
                    data-testid={`${testId}-value`}
                >
                    {value}
                </p>

                {children}
            </CardContent>
        </Card>
    );
}

/**
 * The supporting figures under the headline: plan, forecast, whatever the card compares
 * itself against. Written out rather than drawn, so they read the same to everyone.
 */
export function MetricRows({
    rows,
}: {
    rows: { label: string; value: string }[];
}) {
    return (
        <dl className="space-y-1 text-sm">
            {rows.map((row) => (
                <div key={row.label} className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">{row.label}</dt>
                    <dd className="tabular-nums">{row.value}</dd>
                </div>
            ))}
        </dl>
    );
}
