import { usePage } from '@inertiajs/react';
import {
    CalendarDays,
    LayoutGrid,
    Receipt,
    Scale,
    ShieldCheck,
    Repeat,
    Target,
    Telescope,
    Wallet2,
    TrendingUp,
    Settings,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as cashFlow } from '@/routes/cash-flow';
import { index as comparison } from '@/routes/comparison';
import { show as emergencyFund } from '@/routes/emergency-fund';
import { index as forecast } from '@/routes/forecast';
import { index as goals } from '@/routes/goals';
import { index as months } from '@/routes/months';
import { index as netWorth } from '@/routes/net-worth';
import { show as showPlan } from '@/routes/plan';
import { edit as preferences } from '@/routes/preferences';
import { index as subscriptions } from '@/routes/subscriptions';
import { index as transactions } from '@/routes/transactions';
import type { NavItem } from '@/types';

/**
 * The app's sidebar and top-bar navigation, in one place so both agree.
 *
 * Year-scoped destinations only appear once a year is selected, because without one there
 * is no address to send the user to. Entries are added here as their screens land.
 */
export function useAppNavigation(): NavItem[] {
    const { selectedYear } = usePage().props;

    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Transactions', href: transactions(), icon: Receipt },
    ];

    if (selectedYear !== null) {
        items.push({
            title: 'Months',
            href: months({ year: selectedYear }),
            icon: CalendarDays,
        });

        items.push({
            title: 'Plan',
            href: showPlan({ year: selectedYear, tab: 'income' }),
            icon: Wallet,
        });

        items.push({
            title: 'Plan vs actual',
            href: comparison({ year: selectedYear }),
            icon: Scale,
        });

        items.push({
            title: 'Cash flow',
            href: cashFlow({ year: selectedYear }),
            icon: TrendingUp,
        });

        items.push({
            title: 'Forecast',
            href: forecast({ year: selectedYear }),
            icon: Telescope,
        });
    }

    items.push({
        title: 'Subscriptions',
        href: subscriptions(),
        icon: Repeat,
    });

    items.push({ title: 'Goals', href: goals(), icon: Target });

    items.push({ title: 'Net worth', href: netWorth(), icon: Wallet2 });

    items.push({
        title: 'Emergency fund',
        href: emergencyFund(),
        icon: ShieldCheck,
    });

    items.push({ title: 'Settings', href: preferences(), icon: Settings });

    return items;
}
