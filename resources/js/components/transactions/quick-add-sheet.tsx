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
import { type QuickAddMode, useQuickAdd } from '@/hooks/use-quick-add';
import {
    store as storeTransaction,
    update as updateTransaction,
} from '@/routes/transactions';
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
    reopen_month: boolean;
};

/**
 * The fifteen-second path (§2.3).
 *
 * Three inputs carry the common case — amount, category, description — and everything
 * else already has an answer: expense by default, today's date, the account last reached
 * for. The rest is folded away rather than removed, so the occasional transaction that
 * needs it is still one click from here (UX-07).
 */
export default function QuickAddSheet({
    mode,
    openedAt,
}: {
    mode: QuickAddMode;
    openedAt: number;
}) {
    const { close } = useQuickAdd();
    const isMobile = useIsMobile();
    const { today, formatMoney, formatAmount, formatLocale } = usePreferences();
    const { quickAdd, years } = usePage().props;

    const options = (quickAdd ?? null) as QuickAddOptions | null;
    const source = mode.kind === 'create' ? null : mode.transaction;

    const [choice, setChoice] = useState<CategoryChoice | null>(
        source
            ? {
                  categoryId: source.categoryId,
                  subcategoryId: source.subcategoryId,
                  label: source.subcategoryName
                      ? `${source.categoryName} › ${source.subcategoryName}`
                      : source.categoryName,
              }
            : null,
    );
    const [showMore, setShowMore] = useState(false);
    const amountRef = useRef<HTMLInputElement>(null);

    const form = useForm<Fields>({
        type: source?.type ?? 'expense',
        amount: source ? formatAmount(source.amountCents) : '',
        category_id: source?.categoryId ?? '',
        subcategory_id: source?.subcategoryId ?? '',
        account_id: source?.accountId ?? '',
        // A duplicate is almost always today's version of something that happened before,
        // so the date starts at today rather than the original's (TXF-02).
        occurred_on:
            mode.kind === 'edit' && source ? source.occurredOn : today(),
        description: source?.description ?? '',
        notes: source?.notes ?? '',
        entry_source:
            mode.kind === 'duplicate'
                ? 'duplicate'
                : mode.kind === 'edit'
                  ? 'form'
                  : 'quick_add',
        entry_duration_ms: '',
        reopen_month: false,
    });

    // The sheet only exists while it is open, so this is the moment the options are
    // wanted; most page loads never pay for them (TXQ-01).
    useEffect(() => {
        if (!options) {
            router.reload({ only: ['quickAdd'] });
        }
    }, [options]);

    // Seeded once, the moment the options land. A ref rather than a check on the current
    // value, so a user who deliberately clears the account does not have it put back.
    const seededAccount = useRef(false);

    useEffect(() => {
        if (!options || seededAccount.current || mode.kind !== 'create') {
            return;
        }

        seededAccount.current = true;
        form.setData('account_id', options.defaultAccountId ?? '');
    }, [options, mode.kind, form]);

    const plannedYears = years.map((year) => year.year);
    const datedYear = Number(form.data.occurred_on.slice(0, 4));
    const hasNoPlan =
        Number.isFinite(datedYear) && !plannedYears.includes(datedYear);

    const submit = (again: boolean, reopenMonth = false) => {
        form.transform((data) => ({
            ...data,
            reopen_month: reopenMonth,
            entry_duration_ms: Date.now() - openedAt,
        }));

        const visitOptions = {
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
        };

        if (mode.kind === 'edit' && source) {
            form.patch(updateTransaction.url(source.id), visitOptions);

            return;
        }

        form.post(storeTransaction.url(), visitOptions);
    };

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        submit(false);
    };

    // A finished month is refused by marking the very field that would lift the refusal,
    // so the offer to reopen is driven by the form's own errors (TXV-02).
    const blockedMonth = Boolean(form.errors.reopen_month);

    const monthName = form.data.occurred_on
        ? new Intl.DateTimeFormat(formatLocale, { month: 'long' }).format(
              new Date(`${form.data.occurred_on}T00:00:00`),
          )
        : '';

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

            {blockedMonth && (
                <div
                    className="rounded-md border border-amber-500/50 p-3 text-sm"
                    data-testid="reopen-and-save"
                >
                    <p className="text-amber-700 dark:text-amber-400">
                        {form.errors.occurred_on}
                    </p>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        className="mt-2"
                        disabled={form.processing}
                        onClick={() => submit(false, true)}
                        data-testid="reopen-and-save-button"
                    >
                        Reopen {monthName} and save
                    </Button>
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    disabled={form.processing}
                    data-testid="quick-add-save"
                >
                    {mode.kind === 'edit' ? 'Save changes' : 'Save'}
                </Button>
                {mode.kind !== 'edit' && (
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={form.processing}
                        onClick={() => submit(true)}
                        data-testid="quick-add-save-another"
                    >
                        Save &amp; add another
                    </Button>
                )}
            </div>

            <p className="sr-only">
                Press Enter to save, or Control and Enter to save and add
                another.
            </p>
        </form>
    );

    const title =
        mode.kind === 'edit'
            ? 'Edit transaction'
            : mode.kind === 'duplicate'
              ? 'Duplicate transaction'
              : 'New transaction';

    const description =
        mode.kind === 'duplicate'
            ? 'Same details, dated today. Change anything that differs.'
            : `Amounts are read in your ${formatLocale} format.`;

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
