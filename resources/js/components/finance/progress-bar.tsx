import { cn } from '@/lib/utils';

/**
 * How far along something is.
 *
 * A plain element rather than a component library's: the bar is two divs, and the one
 * shadcn offers would pull in a dependency the PRD has not approved. The value is also
 * written out beside it, because a bar on its own tells a screen reader nothing.
 */
export default function ProgressBar({
    current,
    target,
    label,
    className,
}: {
    current: number;
    target: number;
    label: string;
    className?: string;
}) {
    // A target of nothing is already met; dividing by it is not a question worth asking.
    const share = target <= 0 ? 1 : Math.min(1, current / target);
    const percent = Math.round(share * 1000) / 10;

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            aria-label={label}
            className={cn(
                'h-2 w-full overflow-hidden rounded-full bg-muted',
                className,
            )}
            data-testid="progress-bar"
        >
            <div
                className="h-full rounded-full bg-status-ok transition-[width]"
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}
