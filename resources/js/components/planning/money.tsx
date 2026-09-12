import { usePreferences } from '@/hooks/use-preferences';
import { cn } from '@/lib/utils';

/**
 * An amount, formatted in the reader's own locale. Negative values are muted rather than
 * coloured alone, so the sign is never the only signal.
 */
export default function Money({
    cents,
    className,
    showSign = false,
}: {
    cents: number;
    className?: string;
    showSign?: boolean;
}) {
    const { formatMoney } = usePreferences();
    const isNegative = cents < 0;

    return (
        <span
            className={cn(
                'tabular-nums',
                isNegative && 'text-destructive',
                className,
            )}
        >
            {isNegative && '−'}
            {showSign && !isNegative && cents !== 0 && '+'}
            {formatMoney(Math.abs(cents))}
        </span>
    );
}
