import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Step = { key: string; label: string; href: string; done?: boolean };

/**
 * The wizard's steps, or the plan's tabs. Shows which are finished, so returning picks up
 * where the user left off rather than at the start.
 */
export default function YearNav({
    steps,
    current,
    label = 'Plan sections',
}: {
    steps: Step[];
    current: string;
    label?: string;
}) {
    return (
        <nav className="flex flex-wrap gap-1" aria-label={label}>
            {steps.map((step) => (
                <Button
                    key={step.key}
                    size="sm"
                    variant={step.key === current ? 'default' : 'ghost'}
                    asChild
                >
                    <Link href={step.href} data-testid={`tab-${step.key}`}>
                        {step.label}
                        {step.done && step.key !== current && (
                            <span
                                className={cn('ml-1 text-xs')}
                                aria-label="finished"
                            >
                                ✓
                            </span>
                        )}
                    </Link>
                </Button>
            ))}
        </nav>
    );
}
