import { router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import QuickAddForm from '@/components/transactions/quick-add-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePreferences } from '@/hooks/use-preferences';
import { type QuickAddMode, useQuickAdd } from '@/hooks/use-quick-add';
import {
    store as storeTransaction,
    update as updateTransaction,
} from '@/routes/transactions';
import type { CategoryChoice, QuickAddOptions } from '@/types/quick-add';

export type Fields = {
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

    const monthName = form.data.occurred_on
        ? new Intl.DateTimeFormat(formatLocale, { month: 'long' }).format(
              new Date(`${form.data.occurred_on}T00:00:00`),
          )
        : '';

    const body = (
        <QuickAddForm
            form={form}
            mode={mode}
            options={options}
            choice={choice}
            setChoice={setChoice}
            showMore={showMore}
            setShowMore={setShowMore}
            amountRef={amountRef}
            hasNoPlan={hasNoPlan}
            datedYear={datedYear}
            monthName={monthName}
            formatMoney={formatMoney}
            onSubmit={onSubmit}
            submit={submit}
        />
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

/** The form object the sheet owns and the fields component reads. */
export type QuickAddFormState = ReturnType<typeof useForm<Fields>>;
