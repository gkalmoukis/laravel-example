import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import CategoryCombobox from '@/components/transactions/category-combobox';
import QuickAddDate from '@/components/transactions/quick-add-date';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { QuickAddMode } from '@/hooks/use-quick-add';
import type { CategoryChoice, QuickAddOptions } from '@/types/quick-add';
import type { QuickAddFormState } from './quick-add-sheet';

/**
 * The fields, separated from the shell that holds them.
 *
 * The sheet owns the form object, submitting, and whether it is a dialog or a bottom
 * sheet; this owns what the user actually fills in. They were one 496-line file, which
 * made the three-input common case hard to see among the plumbing (§2.3).
 */
export default function QuickAddForm({
    form,
    mode,
    options,
    choice,
    setChoice,
    showMore,
    setShowMore,
    amountRef,
    hasNoPlan,
    datedYear,
    monthName,
    formatMoney,
    onSubmit,
    submit,
}: {
    form: QuickAddFormState;
    mode: QuickAddMode;
    options: QuickAddOptions | null;
    choice: CategoryChoice | null;
    setChoice: (choice: CategoryChoice | null) => void;
    showMore: boolean;
    setShowMore: (open: boolean) => void;
    amountRef: React.RefObject<HTMLInputElement | null>;
    hasNoPlan: boolean;
    datedYear: number;
    monthName: string;
    formatMoney: (cents: number) => string;
    onSubmit: (event: FormEvent) => void;
    submit: (again: boolean, reopenMonth?: boolean) => void;
}) {
    // A finished month is refused by marking the very field that would lift the refusal,
    // so the offer to reopen is driven by the form's own errors (TXV-02).
    const blockedMonth = Boolean(form.errors.reopen_month);

    return (
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
                    className="mt-1 h-12 text-lg md:h-9 md:text-base"
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

            <QuickAddDate
                value={form.data.occurred_on}
                onChange={(date) => form.setData('occurred_on', date)}
                error={form.errors.occurred_on}
                warning={
                    hasNoPlan
                        ? `You don't have a ${datedYear} plan yet. This transaction won't count in reports until you create it.`
                        : undefined
                }
            />

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
                    className="rounded-md border border-status-warning/50 p-3 text-sm"
                    data-testid="reopen-and-save"
                >
                    <p className="text-status-warning">
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
}
