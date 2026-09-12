import { usePage } from '@inertiajs/react';
import { formatDate, todayIn } from '@/lib/dates';
import { formatAmount, formatMoney } from '@/lib/money';

const fallback = {
    currency: 'EUR',
    formatLocale: 'el-GR',
    timezone: 'Europe/Athens',
};

/**
 * The viewer's formatting preferences, plus the helpers bound to them, so pages never
 * have to thread the locale through themselves.
 */
export function usePreferences() {
    const { preferences } = usePage().props;
    const resolved = preferences ?? fallback;

    return {
        ...resolved,
        formatMoney: (cents: number) =>
            formatMoney(cents, resolved.formatLocale, resolved.currency),
        formatAmount: (cents: number) =>
            formatAmount(cents, resolved.formatLocale),
        formatDate: (isoDate: string) =>
            formatDate(isoDate, resolved.formatLocale),
        today: () => todayIn(resolved.timezone),
    };
}
