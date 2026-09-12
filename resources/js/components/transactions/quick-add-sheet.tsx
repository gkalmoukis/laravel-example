import { router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import CategoryCombobox from '@/components/transactions/category-combobox';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePreferences } from '@/hooks/use-preferences';
import { useQuickAdd } from '@/hooks/use-quick-add';
import { store as storeTransaction } from '@/routes/transactions';
import type { CategoryChoice, QuickAddOptions } from '@/types/quick-add';

type Fields = {
    type: string;
    amount: string;
    category_id: number | '';
    subcategory_id: number | '';
    account_id: number | '';
    occurred_on: string;
    description: string;
    notes: string;
    entry_source: string;
    entry_duration_ms: number | '';
};

/**
 * The fifteen-second path (§2.3).
 *
 * Three inputs carry the common case — amount, category, description — and everything
 * else already has an answer: expense by default, today's date, the account last reached
 * for. The rest is folded away rather than removed, so the occasional transaction that
 * needs it is still one click from here (UX-07).
 */
export default function QuickAddSheet() {
    const { isOpen, close, openedAt } = useQuickAdd();
    const isMobile = useIsMobile();
    const { today, formatMoney, formatLocale } = usePreferences();
    const { quickAdd, years } = usePage().props;

    const options = (quickAdd ?? null) as QuickAddOptions | null;

    const [choice, setChoice] = useState<CategoryChoice | null>(null);
    const [showMore, setShowMore] = useState(false);
    const amountRef = useRef<HTMLInputElement>(null);

    const form = useForm<Fields>({
        type: 'expense',
        amount: '',
        category_id: '',
        subcategory_id: '',
        account_id: '',
        occurred_on: today(),
        description: '',
        notes: '',
        entry_source: 'quick_add',
        entry_duration_ms: '',
    });

    // The options are only fetched when the sheet is actually opened, so most page loads
    // never pay for them (TXQ-01).
    useEffect(() => {
        if (isOpen && !options) {
            router.reload({ only: ['quickAdd'] });
        }
    }, [isOpen, options]);

    useEffect(() => {
        if (isOpen && options && form.data.account_id === '') {
            form.setData('account_id', options.defaultAccountId ?? '');
        }
    }, [isOpen, options]);

    if (!isOpen) {
        return null;
    }

    const plannedYears = years.map((year) => year.year);
    const datedYear = Number(form.data.occurred_on.slice(0, 4));
    const hasNoPlan =
        Number.isFinite(datedYear) && !plannedYears.includes(datedYear);

    const submit = (again: boolean) => {
        form.transform((data) => ({
            ...data,
            entry_duration_ms: openedAt ? Date.now() - openedAt : '',
        }));

        form.post(storeTransaction.url(), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `${form.data.type === 'income' ? 'Income' : 'Expense'} ${formatMoney(
                        Math.round(
                            Number(form.data.amount.replace(',', '.')) * 100,
                        ) || 0,
                    )} · ${choice?.label ?? ''} saved`,
                );

                if (!again) {
                    close();

                    return;
                }

                // Save & add another keeps what usually repeats and clears what does not
                // (TXQ-06).
                form.setData('amount', '');
                form.setData('description', '');
                form.setData('category_id', '');
                form.setData('subcategory_id', '');
                setChoice(null);
                amountRef.current?.focus();
            },
        });
    };

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        submit(false);
    };

    const body = (
        <form
            onSubmit={onSubmit}
            className="space-y-4"
            data-testid="quick-add-form"
        >
            <ToggleGroup
                type="single"
                value={form.data.type}
                onValueChange={(value) => {
                    if (value) {
                        form.setData('type', value);
                        form.setData('category_id', '');
                        form.setData('subcategory_id', '');
                        setChoice(null);
                    }
                }}
                variant="outline"
                className="w-full"
            >
                <ToggleGroupItem
                    value="expense"
                    className="flex-1"
                    data-testid="type-expense"
                >
                    Expense
                </ToggleGroupItem>
                <ToggleGroupItem
                    value="income"
                    className="flex-1"
                    data-testid="type-income"
                >
                    Income
                </ToggleGroupItem>
            </ToggleGroup>

            <div>
                <Label htmlFor="amount">Amount</Label>
                <Input
                    id="amount"
                    name="amount"
                    ref={amountRef}
                    inputMode="decimal"
                    autoFocus
                    autoComplete="off"
                    className="mt-1"
                    placeholder={formatMoney(0).replace(/\d/g, '0')}
                    value={form.data.amount}
                    onChange={(event) =>
                        form.setData('amount', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.amount)}
                />
                <InputError message={form.errors.amount} />
                <p className="mt-1 text-xs text-muted-foreground">
                    Type it the way you would say it — 12,50 or 1.234,56.
                </p>
            </div>

            <div>
                <Label htmlFor="category">Category</Label>
                <div className="mt-1">
                    {options ? (
                        <CategoryCombobox
                            options={options}
                            type={form.data.type}
                            value={choice}
                            invalid={Boolean(form.errors.category_id)}
                            onChange={(next) => {
                                setChoice(next);
                                form.setData('category_id', next.categoryId);
                                form.setData(
                                    'subcategory_id',
                                    next.subcategoryId ?? '',
                                );
                            }}
                        />
                    ) : (
                        <div className="h-9 animate-pulse rounded-md bg-muted" />
                    )}
                </div>
                <InputError message={form.errors.category_id} />
            </div>

            <div>
                <Label htmlFor="description">Description</Label>
                <Input
                    id="description"
                    name="description"
                    autoComplete="off"
                    className="mt-1"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.description)}
                />
                <InputError message={form.errors.description} />
            </div>

            <div>
                <Label htmlFor="occurred_on">Date</Label>
                <Input
                    id="occurred_on"
                    name="occurred_on"
                    type="date"
                    className="mt-1"
                    value={form.data.occurred_on}
                    onChange={(event) =>
                        form.setData('occurred_on', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.occurred_on)}
                />
                <InputError message={form.errors.occurred_on} />

                {hasNoPlan && (
                    <p
                        className="mt-1 text-xs text-amber-700 dark:text-amber-400"
                        data-testid="no-plan-warning"
                    >
                        You don't have a {datedYear} plan yet. This transaction
                        won't count in reports until you create it.
                    </p>
                )}
            </div>

            <Collapsible open={showMore} onOpenChange={setShowMore}>
                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        data-testid="more-details"
                    >
                        {showMore ? 'Fewer details' : 'More details'}
                    </Button>
                </CollapsibleTrigger>

                <CollapsibleContent className="space-y-4 pt-2">
                    {options && options.accounts.length > 0 && (
                        <div>
                            <Label htmlFor="account_id">Account</Label>
                            <Select
                                value={
                                    form.data.account_id === ''
                                        ? 'none'
                                        : String(form.data.account_id)
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'account_id',
                                        value === 'none' ? '' : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="account_id"
                                    className="mt-1 w-full"
                                >
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
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
                        <Label htmlFor="notes">Notes</Label>
                        <Input
                            id="notes"
                            name="notes"
                            className="mt-1"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </CollapsibleContent>
            </Collapsible>

            <div className="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    disabled={form.processing}
                    data-testid="quick-add-save"
                >
                    Save
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    disabled={form.processing}
                    onClick={() => submit(true)}
                    data-testid="quick-add-save-another"
                >
                    Save &amp; add another
                </Button>
            </div>

            <p className="sr-only">
                Press Enter to save, or Control and Enter to save and add
                another.
            </p>
        </form>
    );

    const title = 'New transaction';
    const description = `Amounts are read in your ${formatLocale} format.`;

    if (isMobile) {
        return (
            <Sheet open onOpenChange={(next) => !next && close()}>
                <SheetContent
                    side="bottom"
                    className="max-h-[90vh] overflow-y-auto"
                >
                    <SheetHeader>
                        <SheetTitle>{title}</SheetTitle>
                        <SheetDescription>{description}</SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-6">{body}</div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Dialog open onOpenChange={(next) => !next && close()}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-md"
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        event.preventDefault();
                        submit(true);
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {body}
            </DialogContent>
        </Dialog>
    );
}
