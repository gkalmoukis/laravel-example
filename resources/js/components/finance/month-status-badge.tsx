import { CircleCheck, CircleDashed, CircleDot } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type Status = 'not_started' | 'in_progress' | 'complete';

/**
 * Whether a month has been finished (MON-01).
 *
 * Status is never colour alone: each carries an icon and a word, so the distinction
 * survives a colour-blind reader (FE-14).
 */
const STATUSES: Record<
    Status,
    { label: string; icon: typeof CircleDot; className: string }
> = {
    not_started: {
        label: 'Not started',
        icon: CircleDashed,
        className: 'text-muted-foreground',
    },
    in_progress: {
        label: 'In progress',
        icon: CircleDot,
        className: 'text-amber-700 dark:text-amber-400',
    },
    complete: {
        label: 'Complete',
        icon: CircleCheck,
        className: 'text-emerald-700 dark:text-emerald-400',
    },
};

export default function MonthStatusBadge({ status }: { status: string }) {
    const resolved = STATUSES[status as Status] ?? STATUSES.not_started;
    const Icon = resolved.icon;

    return (
        <Badge variant="outline" className={`gap-1 ${resolved.className}`}>
            <Icon className="size-3" aria-hidden="true" />
            {resolved.label}
        </Badge>
    );
}
