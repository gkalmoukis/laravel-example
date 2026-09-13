import { usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChartNoAxesCombined,
    LayoutGrid,
    Receipt,
    Settings,
    Target,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as comparison } from '@/routes/comparison';
import { index as goals } from '@/routes/goals';
import { index as months } from '@/routes/months';
import { show as showPlan } from '@/routes/plan';
import { edit as preferences } from '@/routes/preferences';
import { index as subscriptions } from '@/routes/subscriptions';
import { index as transactions } from '@/routes/transactions';
import type { NavItem } from '@/types';

/**
 * The app's sidebar and top-bar navigation, in one place so both agree.
 *
 * Seven entries, not the twelve this started as. Plan vs actual, cash flow and forecast
 * are one Reports hub; goals, the emergency fund and net worth are one Goals hub; and
 * subscriptions are a tab of the plan. Each entry opens its hub's first tab and stays lit
 * across the rest of it, so the sidebar says where you are rather than which tab you
 * happen to have open (UX-05, UX-09).
 *
 * Year-scoped destinations only appear once a year is selected, because without one there
 * is no address to send the user to.
 */
export function useAppNavigation(): NavItem[] {
    const { selectedYear } = usePage().props;

    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Transactions', href: transactions(), icon: Receipt },
    ];

    if (selectedYear !== null) {
        items.push({
            title: 'Plan',
            href: showPlan({ year: selectedYear, tab: 'income' }),
            icon: Wallet,
            match: [`/years/${selectedYear}/plan`, subscriptions.url()],
        });

        items.push({
            title: 'Months',
            href: months({ year: selectedYear }),
            icon: CalendarDays,
        });

        items.push({
            title: 'Reports',
            href: comparison({ year: selectedYear }),
            icon: ChartNoAxesCombined,
            match: [`/years/${selectedYear}/reports`],
        });
    } else {
        // Without a year the plan tabs have no address, but subscriptions still do, and
        // they are the one part of the plan a user can fill in before making one.
        items.push({
            title: 'Plan',
            href: subscriptions(),
            icon: Wallet,
        });
    }

    items.push({ title: 'Goals', href: goals(), icon: Target });

    // Settings opens on preferences but owns every screen under /settings, the
    // foundation's profile and security pages included.
    items.push({
        title: 'Settings',
        href: preferences(),
        icon: Settings,
        match: ['/settings'],
    });

    return items;
}
