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
