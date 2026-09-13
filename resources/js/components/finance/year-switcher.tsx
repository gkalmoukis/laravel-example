import { Link, router, usePage } from '@inertiajs/react';
import { CalendarRange, Check, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { create as createYear } from '@/routes/financial-years';
import { show as showPlan } from '@/routes/plan';

/**
 * Which year everything on screen is about (YEAR-07).
 *
 * Picking a year goes to that year's plan, because it is the one screen every year has;
 * the choice is then remembered server-side and carries to the screens whose address has
 * no year in it.
 */
export default function YearSwitcher() {
    const { years, selectedYear } = usePage().props;

    if (years.length === 0) {
        return (
            <Button variant="outline" size="sm" asChild>
                <Link href={createYear()} data-testid="create-first-year">
                    <Plus className="size-4" />
                    Create your first plan
                </Link>
            </Button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="year-switcher"
                    aria-label={`Selected year: ${selectedYear ?? 'none'}`}
                >
                    <CalendarRange className="size-4" />
                    {selectedYear ?? 'Pick a year'}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-44">
                {years.map((year) => (
                    <DropdownMenuItem
                        key={year.year}
                        onSelect={() =>
                            router.visit(
                                showPlan({ year: year.year, tab: 'income' }),
                            )
                        }
                    >
                        {year.year === selectedYear ? (
                            <Check className="size-4" />
                        ) : (
                            <span className="size-4" />
                        )}
                        {year.year}
                        {!year.isSetupComplete && (
                            <span className="ml-auto text-xs text-muted-foreground">
                                unfinished
                            </span>
                        )}
                    </DropdownMenuItem>
                ))}

                <DropdownMenuSeparator />

                <DropdownMenuItem onSelect={() => router.visit(createYear())}>
                    <Plus className="size-4" />
                    New plan
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
