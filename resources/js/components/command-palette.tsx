import { router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarRange,
    ChartNoAxesCombined,
    LayoutGrid,
    Plus,
    Receipt,
    Settings,
    Target,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { useQuickAdd } from '@/hooks/use-quick-add';
import { dashboard } from '@/routes';
import { index as cashFlow } from '@/routes/cash-flow';
import { index as comparison } from '@/routes/comparison';
import { show as emergencyFund } from '@/routes/emergency-fund';
import { create as createYear } from '@/routes/financial-years';
import { index as forecast } from '@/routes/forecast';
import { index as goals } from '@/routes/goals';
import { index as months } from '@/routes/months';
import { index as netWorth } from '@/routes/net-worth';
import { show as showPlan } from '@/routes/plan';
import { edit as preferences } from '@/routes/preferences';
import { index as subscriptions } from '@/routes/subscriptions';
import { index as transactions } from '@/routes/transactions';

type Destination = {
    label: string;
    href: string;
    icon: typeof LayoutGrid;
    /** Extra words to match on, for tabs whose name is not what you would type. */
    keywords?: string;
};

/**
 * Every screen, every year and the one thing you came to do, behind one key.
 *
 * Seven sidebar entries cover twelve screens, so the tabs inside a hub are a level down
 * and take two clicks to reach. ⌘K reaches any of them by name (UX-05, NFR-06).
 */
export default function CommandPalette() {
    const { years, selectedYear } = usePage().props;
    const { open: openQuickAdd } = useQuickAdd();

    const [open, setOpen] = useState(false);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key !== 'k' && event.key !== 'K') {
                return;
            }

            // A modifier combination cannot be typed by accident, so unlike the bare `N`
            // shortcut this one stays available while the user is in a field — which is
            // exactly where a palette is usually reached for.
            if (!event.metaKey && !event.ctrlKey) {
                return;
            }

            event.preventDefault();
            setOpen((current) => !current);
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    const go = (href: string) => {
        setOpen(false);
        router.visit(href);
    };

    const destinations: Destination[] = [
        { label: 'Dashboard', href: dashboard.url(), icon: LayoutGrid },
        { label: 'Transactions', href: transactions.url(), icon: Receipt },
        { label: 'Goals', href: goals.url(), icon: Target },
        {
            label: 'Emergency fund',
            href: emergencyFund.url(),
            icon: Target,
            keywords: 'goals safety net',
        },
        {
            label: 'Net worth',
            href: netWorth.url(),
            icon: Target,
            keywords: 'goals assets debts',
        },
        {
            label: 'Subscriptions',
            href: subscriptions.url(),
            icon: Wallet,
            keywords: 'plan recurring',
        },
        {
            label: 'Settings',
            href: preferences.url(),
            icon: Settings,
            keywords: 'preferences categories accounts',
        },
    ];

    if (selectedYear !== null) {
        destinations.push(
            {
                label: 'Plan · Income',
                href: showPlan.url({ year: selectedYear, tab: 'income' }),
                icon: Wallet,
            },
            {
                label: 'Plan · Monthly budget',
                href: showPlan.url({ year: selectedYear, tab: 'expenses' }),
                icon: Wallet,
                keywords: 'plan expenses grid',
            },
            {
                label: 'Plan · Irregular',
                href: showPlan.url({ year: selectedYear, tab: 'irregular' }),
                icon: Wallet,
            },
            {
                label: 'Plan · Opening position',
                href: showPlan.url({ year: selectedYear, tab: 'opening' }),
                icon: Wallet,
            },
            {
                label: 'Months',
                href: months.url({ year: selectedYear }),
                icon: CalendarDays,
            },
            {
                label: 'Reports · Plan vs actual',
                href: comparison.url({ year: selectedYear }),
                icon: ChartNoAxesCombined,
                keywords: 'variance comparison',
            },
            {
                label: 'Reports · Cash flow',
                href: cashFlow.url({ year: selectedYear }),
                icon: ChartNoAxesCombined,
                keywords: 'balance',
            },
            {
                label: 'Reports · Forecast',
                href: forecast.url({ year: selectedYear }),
                icon: ChartNoAxesCombined,
                keywords: 'year end',
            },
        );
    }

    return (
        <CommandDialog
            open={open}
            onOpenChange={setOpen}
            title="Search"
            description="Jump to a screen, switch year, or record a transaction."
        >
            <CommandInput placeholder="Where to?" />

            <CommandList>
                <CommandEmpty>Nothing matches that.</CommandEmpty>

                <CommandGroup heading="Do">
                    <CommandItem
                        value="new transaction record expense income"
                        onSelect={() => {
                            setOpen(false);
                            openQuickAdd();
                        }}
                        data-testid="palette-new-transaction"
                    >
                        <Plus />
                        New transaction
                        <CommandShortcut>N</CommandShortcut>
                    </CommandItem>
                </CommandGroup>

                <CommandSeparator />

                <CommandGroup heading="Go to">
                    {destinations.map((destination) => (
                        <CommandItem
                            key={destination.label}
                            value={`${destination.label} ${destination.keywords ?? ''}`}
                            onSelect={() => go(destination.href)}
                        >
                            <destination.icon />
                            {destination.label}
                        </CommandItem>
                    ))}
                </CommandGroup>

                {years.length > 0 && (
                    <>
                        <CommandSeparator />

                        <CommandGroup heading="Year">
                            {years.map((year) => (
                                <CommandItem
                                    key={year.year}
                                    value={`year ${year.year}`}
                                    onSelect={() =>
                                        go(
                                            showPlan.url({
                                                year: year.year,
                                                tab: 'income',
                                            }),
                                        )
                                    }
                                    data-testid={`palette-year-${year.year}`}
                                >
                                    <CalendarRange />
                                    {year.year}
                                    {year.year === selectedYear && (
                                        <CommandShortcut>
                                            current
                                        </CommandShortcut>
                                    )}
                                </CommandItem>
                            ))}

                            <CommandItem
                                value="new plan year create"
                                onSelect={() => go(createYear.url())}
                            >
                                <Plus />
                                New plan
                            </CommandItem>
                        </CommandGroup>
                    </>
                )}
            </CommandList>
        </CommandDialog>
    );
}
