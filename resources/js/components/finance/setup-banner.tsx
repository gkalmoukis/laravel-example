import { Link, usePage } from '@inertiajs/react';
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

/**
 * The same banner, wherever a year is on screen without being named in the props
 * (EDGE-02).
 *
 * Reads the selected year from the shared props and draws nothing when that year was
 * finished, so a screen can carry the reminder with one line and no plumbing.
 */
export function SelectedYearSetupBanner() {
    const { years, selectedYear } = usePage().props;

    const year = years.find((candidate) => candidate.year === selectedYear);

    if (year === undefined || year.isSetupComplete) {
        return null;
    }

    return <SetupBanner year={year.year} />;
}
