import { usePage } from '@inertiajs/react';
import { LayoutGrid, Receipt, Settings, Wallet } from 'lucide-react';
import { dashboard } from '@/routes';
import { show as showPlan } from '@/routes/plan';
import { edit as preferences } from '@/routes/preferences';
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
            title: 'Plan',
            href: showPlan({ year: selectedYear, tab: 'income' }),
            icon: Wallet,
        });
    }

    items.push({ title: 'Settings', href: preferences(), icon: Settings });

    return items;
}
