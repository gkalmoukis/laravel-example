import { router } from '@inertiajs/react';
import { ListFilter, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePreferences } from '@/hooks/use-preferences';
import { filterQuery, narrowingFilterCount } from '@/lib/transaction-filters';
import { index as transactionsIndex } from '@/routes/transactions';
import type {
    TransactionFilters as Filters,
    TransactionOptions,
} from '@/types/transactions';

const ANY = 'any';

type Change = Record<string, string | number | boolean | null>;

/**
 * Filter state lives in the address, not in this component (TXL-01).
 *
 * That is what makes a filtered list linkable: every "fix it in the transaction" link from
 * a report is just a URL, and the back button behaves the way the user expects.
 *
 * Eleven controls used to sit open at once, which pushed the list itself off the screen.
 * The search is in front because it is what people reach for; whatever is actually
 * narrowing the list shows as a chip that can be taken off; the rest is behind one button
 * that says how many are set (UX-07, UX-09).
 */
export default function TransactionFilters({
    filters,
    options,
}: {
    filters: Filters;
    options: TransactionOptions;
}) {
    const { formatAmount, formatDate } = usePreferences();

    const [search, setSearch] = useState(filters.q ?? '');
    const [open, setOpen] = useState(false);

    const apply = (changes: Change) => {
        router.get(
            transactionsIndex.url(),
            filterQuery(filters, formatAmount, changes),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const category = options.categories.find(
        (candidate) => candidate.id === filters.categoryId,
    );

    const subcategories = category?.subcategories ?? [];

    const active = narrowingFilterCount(filters);

    const chips: { key: string; label: string; clears: Change }[] = [];

    if (filters.type !== null) {
        chips.push({
            key: 'type',
            label: filters.type === 'income' ? 'Income' : 'Expense',
            clears: { type: null },
        });
    }

    if (category !== undefined) {
        chips.push({
            key: 'category',
            label: category.name,
            clears: { category_id: null, subcategory_id: null },
        });
    }

    const subcategory = subcategories.find(
        (candidate) => candidate.id === filters.subcategoryId,
    );

    if (subcategory !== undefined) {
        chips.push({
            key: 'subcategory',
            label: subcategory.name,
            clears: { subcategory_id: null },
        });
    }

    const account = options.accounts.find(
        (candidate) => candidate.id === filters.accountId,
    );

    if (account !== undefined) {
        chips.push({
            key: 'account',
            label: account.name,
            clears: { account_id: null },
        });
    }

    if (filters.from !== null) {
        chips.push({
            key: 'from',
            label: `From ${formatDate(filters.from)}`,
            clears: { from: null },
        });
    }

    if (filters.to !== null) {
        chips.push({
            key: 'to',
            label: `To ${formatDate(filters.to)}`,
            clears: { to: null },
        });
    }

    if (filters.minAmount !== null) {
        chips.push({
            key: 'min',
            label: `At least ${formatAmount(filters.minAmount)}`,
            clears: { min_amount: null },
        });
    }

    if (filters.maxAmount !== null) {
        chips.push({
            key: 'max',
            label: `At most ${formatAmount(filters.maxAmount)}`,
            clears: { max_amount: null },
        });
    }

    if (filters.onlyIssues) {
        chips.push({
            key: 'issues',
            label: 'Only with issues',
            clears: { issues: null },
        });
    }

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="flex min-w-0 flex-1 gap-2">
                    <Input
                        id="q"
                        name="q"
                        aria-label="Description contains"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                apply({ q: search });
                            }
                        }}
                        placeholder="Search descriptions…"
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

                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button variant="outline" data-testid="more-filters">
                            <ListFilter className="size-4" />
                            More filters
                            {active > 0 && (
                                <Badge variant="secondary">{active}</Badge>
                            )}
                        </Button>
                    </PopoverTrigger>

                    <PopoverContent
                        align="end"
                        className="grid w-80 gap-3 sm:w-96 sm:grid-cols-2"
                    >
                        <Choice
                            id="type"
                            label="Type"
                            value={filters.type}
                            onChange={(value) => apply({ type: value })}
                            choices={[
                                { value: 'expense', label: 'Expense' },
                                { value: 'income', label: 'Income' },
                            ]}
                        />

                        <Choice
                            id="category_id"
                            label="Category"
                            value={
                                filters.categoryId === null
                                    ? null
                                    : String(filters.categoryId)
                            }
                            onChange={(value) =>
                                apply({
                                    category_id: value,
                                    subcategory_id: null,
                                })
                            }
                            choices={options.categories.map((option) => ({
                                value: String(option.id),
                                label: option.name,
                            }))}
                        />

                        {subcategories.length > 0 && (
                            <Choice
                                id="subcategory_id"
                                label="Subcategory"
                                value={
                                    filters.subcategoryId === null
                                        ? null
                                        : String(filters.subcategoryId)
                                }
                                onChange={(value) =>
                                    apply({ subcategory_id: value })
                                }
                                choices={subcategories.map((option) => ({
                                    value: String(option.id),
                                    label: option.name,
                                }))}
                            />
                        )}

                        {options.accounts.length > 0 && (
                            <Choice
                                id="account_id"
                                label="Account"
                                value={
                                    filters.accountId === null
                                        ? null
                                        : String(filters.accountId)
                                }
                                onChange={(value) =>
                                    apply({ account_id: value })
                                }
                                choices={options.accounts.map((option) => ({
                                    value: String(option.id),
                                    label: option.name,
                                }))}
                            />
                        )}

                        <div className="grid gap-1">
                            <Label htmlFor="from">From</Label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                value={filters.from ?? ''}
                                onChange={(event) =>
                                    apply({ from: event.target.value })
                                }
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="to">To</Label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                value={filters.to ?? ''}
                                onChange={(event) =>
                                    apply({ to: event.target.value })
                                }
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="min_amount">Least</Label>
                            <Input
                                id="min_amount"
                                name="min_amount"
                                inputMode="decimal"
                                // Cents on the wire, a typed amount in the field: the
                                // request reads this back through Money::fromInput.
                                defaultValue={
                                    filters.minAmount === null
                                        ? ''
                                        : formatAmount(filters.minAmount)
                                }
                                onBlur={(event) =>
                                    apply({ min_amount: event.target.value })
                                }
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="max_amount">Most</Label>
                            <Input
                                id="max_amount"
                                name="max_amount"
                                inputMode="decimal"
                                defaultValue={
                                    filters.maxAmount === null
                                        ? ''
                                        : formatAmount(filters.maxAmount)
                                }
                                onBlur={(event) =>
                                    apply({ max_amount: event.target.value })
                                }
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <Checkbox
                                checked={filters.onlyIssues}
                                onCheckedChange={(checked) =>
                                    apply({
                                        issues: checked === true ? '1' : null,
                                    })
                                }
                                data-testid="only-issues"
                            />
                            Only with issues
                        </label>
                    </PopoverContent>
                </Popover>

                {(active > 0 || filters.q !== null) && (
                    <Button
                        variant="ghost"
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
                )}
            </div>

            {chips.length > 0 && (
                <ul className="flex flex-wrap gap-2" data-testid="filter-chips">
                    {chips.map((chip) => (
                        <li key={chip.key}>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => apply(chip.clears)}
                                data-testid={`chip-${chip.key}`}
                            >
                                {chip.label}
                                <X className="size-3" aria-hidden="true" />
                                <span className="sr-only">
                                    Remove this filter
                                </span>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * One optional choice, with "Any" as the way back out of it.
 */
function Choice({
    id,
    label,
    value,
    choices,
    onChange,
}: {
    id: string;
    label: string;
    value: string | null;
    choices: { value: string; label: string }[];
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>

            <Select
                value={value ?? ANY}
                onValueChange={(next) => onChange(next === ANY ? null : next)}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder="Any" />
                </SelectTrigger>

                <SelectContent>
                    <SelectItem value={ANY}>Any</SelectItem>

                    {choices.map((choice) => (
                        <SelectItem key={choice.value} value={choice.value}>
                            {choice.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
