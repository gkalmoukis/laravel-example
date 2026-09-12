import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { show as showSetup } from '@/routes/year-setup';

/**
 * The reminder that a year was never finished (EDGE-02).
 *
 * Plan amounts still work without it, so this nudges rather than blocks: what a half-set-up
 * year is missing is the captured baseline the forecast measures drift against.
 */
export default function SetupBanner({ year }: { year: number }) {
    return (
        <div
            className="flex flex-wrap items-center gap-2 rounded-md border border-dashed p-4 text-sm"
            data-testid="setup-banner"
        >
            <Badge variant="outline">Setup unfinished</Badge>

            <span className="text-muted-foreground">
                You never finished setting {year} up.
            </span>

            <Link
                className="underline underline-offset-4"
                href={showSetup({ year, step: 'opening' })}
            >
                Finish setting up {year}
            </Link>
        </div>
    );
}
