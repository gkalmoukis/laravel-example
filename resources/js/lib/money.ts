/**
 * Money crosses the wire as integer cents and is only ever turned into text here, using
 * the viewer's own format locale. Nothing in the application does arithmetic on the
 * formatted value.
 */
export function formatMoney(
    cents: number,
    locale: string,
    currency = 'EUR',
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(cents / 100);
}

/**
 * The same amount without the currency sign, for table columns where the sign would
 * repeat on every row.
 */
export function formatAmount(cents: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(cents / 100);
}

/**
 * One figure as a share of another, written as a percentage — or "—" when there is
 * nothing meaningful to divide by (EDGE-03).
 *
 * There were two of these, on the dashboard and on the forecast, and they disagreed:
 * one returned a dash for any non-positive denominator, the other only for exactly
 * zero, so a year with negative income showed a negative savings rate on one screen and
 * a dash on the other. They also rounded by different rules.
 *
 * Rounded half up to one decimal for display only, from figures divided at full
 * precision — never from an already-rounded value.
 */
export function formatShare(part: number, whole: number): string {
    if (whole <= 0) {
        return '—';
    }

    return `${Math.round((part / whole) * 1000) / 10}%`;
}
