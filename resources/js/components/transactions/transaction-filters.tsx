import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as transactionsIndex } from '@/routes/transactions';
import type {
    TransactionFilters as Filters,
    TransactionOptions,
} from '@/types/transactions';

const ANY = 'any';

/**
 * Filter state lives in the address, not in this component (TXL-01).
 *
 * That is what makes a filtered list linkable: every "fix it in the transaction" link from
 * a report is just a URL, and the back button behaves the way the user expects.
 */
export default function TransactionFilters({
    filters,
    options,
}: {
    filters: Filters;
    options: TransactionOptions;
}) {
    const [search, setSearch] = useState(filters.q ?? '');

    const apply = (changes: Record<string, string | number | null>) => {
        const next: Record<string, string> = {};

        const merged = {
            from: filters.from,
            to: filters.to,
            month: filters.month,
            year: filters.year,
            type: filters.type,
            category_id: filters.categoryId,
            subcategory_id: filters.subcategoryId,
            account_id: filters.accountId,
            q: filters.q,
            issues: filters.onlyIssues ? '1' : null,
            ...changes,
        };

        for (const [key, value] of Object.entries(merged)) {
            if (value !== null && value !== '') {
                next[key] = String(value);
            }
        }

        router.get(transactionsIndex.url(), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const subcategories =
        options.categories.find((c) => c.id === filters.categoryId)
            ?.subcategories ?? [];

    return (
        <div className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="sm:col-span-2">
                <Label htmlFor="q">Description contains</Label>
                <div className="mt-1 flex gap-2">
                    <Input
                        id="q"
                        name="q"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                apply({ q: search });
                            }
                        }}
                        placeholder="Rent, coffee…"
                    />
                    <Button
                        variant="secondary"
                        onClick={() => apply({ q: search })}
                        data-testid="apply-search"
                    >
                        <Search className="size-4" />
                        <span className="sr-only">Search</span>
                    </Button>
                </div>
            </div>

            <div>
                <Label htmlFor="type">Type</Label>
                <Select
                    value={filters.type ?? ANY}
                    onValueChange={(value) =>
                        apply({ type: value === ANY ? null : value })
                    }
                >
                    <SelectTrigger id="type" className="mt-1 w-full">
                        <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any</SelectItem>
                        <SelectItem value="expense">Expense</SelectItem>
                        <SelectItem value="income">Income</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div>
                <Label htmlFor="category_id">Category</Label>
                <Select
                    value={
                        filters.categoryId ? String(filters.categoryId) : ANY
                    }
                    onValueChange={(value) =>
                        apply({
                            category_id: value === ANY ? null : value,
                            subcategory_id: null,
                        })
                    }
                >
                    <SelectTrigger id="category_id" className="mt-1 w-full">
                        <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any</SelectItem>
                        {options.categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {subcategories.length > 0 && (
                <div>
                    <Label htmlFor="subcategory_id">Subcategory</Label>
                    <Select
                        value={
                            filters.subcategoryId
                                ? String(filters.subcategoryId)
                                : ANY
                        }
                        onValueChange={(value) =>
                            apply({
                                subcategory_id: value === ANY ? null : value,
                            })
                        }
                    >
                        <SelectTrigger
                            id="subcategory_id"
                            className="mt-1 w-full"
                        >
                            <SelectValue placeholder="Any" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any</SelectItem>
                            {subcategories.map((subcategory) => (
                                <SelectItem
                                    key={subcategory.id}
                                    value={String(subcategory.id)}
                                >
                                    {subcategory.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            {options.accounts.length > 0 && (
                <div>
                    <Label htmlFor="account_id">Account</Label>
                    <Select
                        value={
                            filters.accountId ? String(filters.accountId) : ANY
                        }
                        onValueChange={(value) =>
                            apply({ account_id: value === ANY ? null : value })
                        }
                    >
                        <SelectTrigger id="account_id" className="mt-1 w-full">
                            <SelectValue placeholder="Any" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any</SelectItem>
                            {options.accounts.map((account) => (
                                <SelectItem
                                    key={account.id}
                                    value={String(account.id)}
                                >
                                    {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            <div>
                <Label htmlFor="from">From</Label>
                <Input
                    id="from"
                    name="from"
                    type="date"
                    className="mt-1"
                    value={filters.from ?? ''}
                    onChange={(event) => apply({ from: event.target.value })}
                />
            </div>

            <div>
                <Label htmlFor="to">To</Label>
                <Input
                    id="to"
                    name="to"
                    type="date"
                    className="mt-1"
                    value={filters.to ?? ''}
                    onChange={(event) => apply({ to: event.target.value })}
                />
            </div>

            <div>
                <Label htmlFor="min_amount">Least</Label>
                <Input
                    id="min_amount"
                    name="min_amount"
                    inputMode="decimal"
                    className="mt-1"
                    defaultValue={filters.minAmount ?? ''}
                    onBlur={(event) =>
                        apply({ min_amount: event.target.value })
                    }
                />
            </div>

            <div>
                <Label htmlFor="max_amount">Most</Label>
                <Input
                    id="max_amount"
                    name="max_amount"
                    inputMode="decimal"
                    className="mt-1"
                    defaultValue={filters.maxAmount ?? ''}
                    onBlur={(event) =>
                        apply({ max_amount: event.target.value })
                    }
                />
            </div>

            <div className="flex items-end gap-2 sm:col-span-2">
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={filters.onlyIssues}
                        onCheckedChange={(checked) =>
                            apply({ issues: checked === true ? '1' : null })
                        }
                        data-testid="only-issues"
                    />
                    Only with issues
                </label>

                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto"
                    onClick={() => {
                        setSearch('');
                        router.get(
                            transactionsIndex.url(),
                            {},
                            { replace: true },
                        );
                    }}
                    data-testid="clear-filters"
                >
                    <X className="size-4" />
                    Clear
                </Button>
            </div>
        </div>
    );
}
