export type TransactionIssue = {
    key: string;
    reason: string;
};

export type TransactionRow = {
    id: number;
    type: 'income' | 'expense';
    occurredOn: string;
    amountCents: number;
    description: string;
    notes: string | null;
    categoryId: number;
    categoryName: string;
    subcategoryId: number | null;
    subcategoryName: string | null;
    accountId: number | null;
    accountName: string | null;
    subscriptionName: string | null;
    issues: TransactionIssue[];
};

export type TransactionTotals = {
    incomeCents: number;
    expenseCents: number;
    netCents: number;
};

export type TransactionFilters = {
    from: string | null;
    to: string | null;
    month: number | null;
    year: number;
    type: string | null;
    categoryId: number | null;
    subcategoryId: number | null;
    accountId: number | null;
    minAmount: number | null;
    maxAmount: number | null;
    q: string | null;
    onlyIssues: boolean;
};

export type TransactionOptions = {
    categories: {
        id: number;
        name: string;
        type: string;
        subcategories: { id: number; name: string }[];
    }[];
    accounts: { id: number; name: string }[];
};

export type PaginationState = {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
};
