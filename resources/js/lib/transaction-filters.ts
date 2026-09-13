import type { TransactionFilters } from '@/types/transactions';

/**
 * Every filter, keyed the way `IndexTransactionRequest` reads it back.
 *
 * One definition because there were two: the filter form rebuilt the query and so did the
 * pager, and each forgot a different subset — the pager dropped the dates, month,
 * subcategory, account and amounts, the form dropped the amounts. Turning a page or
 * changing one filter quietly widened the list.
 *
 * `minAmount` and `maxAmount` arrive as integer cents but are read back through
 * `Money::fromInput`, which expects a typed amount — so they are formatted on the way
 * out. Sending the cents straight back would read 1250 as 1.250,00.
 */
export function filterQuery(
    filters: TransactionFilters,
    formatAmount: (cents: number) => string,
    changes: Record<string, string | number | boolean | null> = {},
): Record<string, string> {
    const merged: Record<string, string | number | boolean | null> = {
        from: filters.from,
        to: filters.to,
        month: filters.month,
        year: filters.year,
        type: filters.type,
        category_id: filters.categoryId,
        subcategory_id: filters.subcategoryId,
        account_id: filters.accountId,
        min_amount:
            filters.minAmount === null ? null : formatAmount(filters.minAmount),
        max_amount:
            filters.maxAmount === null ? null : formatAmount(filters.maxAmount),
        q: filters.q,
        issues: filters.onlyIssues ? '1' : null,
        ...changes,
    };

    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(merged)) {
        if (value === null || value === '' || value === false) {
            continue;
        }

        query[key] = String(value);
    }

    return query;
}

/**
 * How many filters are narrowing the list, so a collapsed control can say so.
 *
 * The year is always set — it is which year you are looking at rather than a filter —
 * and the search has its own field, so neither is counted here.
 */
export function narrowingFilterCount(filters: TransactionFilters): number {
    const narrowing = [
        filters.from,
        filters.to,
        filters.month,
        filters.type,
        filters.categoryId,
        filters.subcategoryId,
        filters.accountId,
        filters.minAmount,
        filters.maxAmount,
        filters.onlyIssues ? true : null,
    ];

    return narrowing.filter((value) => value !== null && value !== false)
        .length;
}
