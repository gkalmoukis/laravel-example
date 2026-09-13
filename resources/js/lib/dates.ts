/**
 * Dates arrive as plain calendar dates (YYYY-MM-DD) and are formatted with the viewer's
 * locale. Parsing is done from the parts rather than with `new Date(iso)`, which would
 * read the string as UTC midnight and can shift the day backwards west of Greenwich.
 */
export function formatDate(isoDate: string, locale: string): string {
    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return isoDate;
    }

    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}

/**
 * Today in the user's timezone, as a calendar date. "Today" has to follow the user, not
 * the server, or a late-evening transaction lands on the wrong day.
 */
export function todayIn(timezone: string): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
}

/**
 * "3 March" — the way a person says a date out loud, for confirmation copy where a full
 * numeric date would read like a form field rather than a question (TXF-03).
 */
export function formatDayAndMonth(isoDate: string, locale: string): string {
    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return isoDate;
    }

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'long',
    }).format(new Date(year, month - 1, day));
}

/**
 * "Mar" — the short month name in the viewer's locale, for chart axes and table rows
 * where the full name would not fit.
 */
export function shortMonth(month: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, { month: 'short' }).format(
        new Date(2000, month - 1, 1),
    );
}

/**
 * A calendar date shifted by whole days, staying a calendar date.
 *
 * Built from the parts and formatted back the same way, so "yesterday" never drifts into
 * the day before across a timezone boundary.
 */
export function addDays(isoDate: string, days: number): string {
    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return isoDate;
    }

    const shifted = new Date(year, month - 1, day + days);

    return [
        String(shifted.getFullYear()).padStart(4, '0'),
        String(shifted.getMonth() + 1).padStart(2, '0'),
        String(shifted.getDate()).padStart(2, '0'),
    ].join('-');
}
