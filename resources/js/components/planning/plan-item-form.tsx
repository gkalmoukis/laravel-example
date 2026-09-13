import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import MoneyInput from '@/components/planning/money-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Switch } from '@/components/ui/switch';

export type PlanCategoryOption = { id: number; name: string };

export type PlanItemValues = {
    type: string;
    kind: string;
    name: string;
    category_id: string;
    amount: string;
    frequency: string;
    start_month: string;
    payment_day: string;
    allocation: string;
    notes: string;
};

export const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

/**
 * Custom is left out on purpose: it needs a month-by-month picker, and a plan item that
 * lands in an arbitrary set of months is better built by editing the budget grid.
 */
const FREQUENCIES = [
    { value: 'monthly', label: 'Every month' },
    { value: 'quarterly', label: 'Every three months' },
    { value: 'semi_annual', label: 'Every six months' },
    { value: 'annual', label: 'Once a year' },
    { value: 'once', label: 'Once only' },
];

export function blankPlanItem(type: string, kind: string): PlanItemValues {
    return {
        type,
        kind,
        name: '',
        category_id: '',
        amount: '',
        frequency: kind === 'irregular' ? 'annual' : 'monthly',
        start_month: '1',
        payment_day: '',
        allocation: 'lump_sum',
        notes: '',
    };
}

/**
 * The same fields whether a plan item is being added or changed.
 *
 * Until now these lived only inside the setup wizard, which meant the year could be
 * planned once and then never corrected without starting the wizard again. Four fields
 * carry the common case; the rest is folded away rather than removed, so the occasional
 * item that needs a payment day or a spread is still one click from here (UX-07).
 *
 * Only one of these is on screen at a time, so the field ids stay fixed and a label
 * always points at the input the user is looking at.
 */
export default function PlanItemForm({
    title,
    submitLabel,
    url,
    method,
    initial,
    categories,
    amountLabel,
    monthLabel,
    namePlaceholder,
    onDone,
}: {
    title: string;
    submitLabel: string;
    url: string;
    method: 'post' | 'patch';
    initial: PlanItemValues;
    categories: PlanCategoryOption[];
    amountLabel: string;
    monthLabel: string;
    namePlaceholder: string;
    onDone: () => void;
}) {
    const form = useForm<PlanItemValues>(initial);
    const [more, setMore] = useState(false);

    const isIrregular = initial.kind === 'irregular';

    const submit = () => {
        form.submit(method, url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>

            <CardContent>
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="plan-item-name">What is it?</Label>

                            <Input
                                id="plan-item-name"
                                name="name"
                                required
                                placeholder={namePlaceholder}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />

                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="plan-item-category">Category</Label>

                            <Select
                                value={form.data.category_id}
                                onValueChange={(value) =>
                                    form.setData('category_id', value)
                                }
                            >
                                <SelectTrigger id="plan-item-category">
                                    <SelectValue placeholder="Pick a category" />
                                </SelectTrigger>

                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <InputError message={form.errors.category_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="plan-item-amount">
                                {amountLabel}
                            </Label>

                            <MoneyInput
                                id="plan-item-amount"
                                name="amount"
                                required
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />

                            <InputError message={form.errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="plan-item-month">
                                {monthLabel}
                            </Label>

                            <Select
                                value={form.data.start_month}
                                onValueChange={(value) =>
                                    form.setData('start_month', value)
                                }
                            >
                                <SelectTrigger id="plan-item-month">
                                    <SelectValue />
                                </SelectTrigger>

                                <SelectContent>
                                    {MONTH_NAMES.map((name, index) => (
                                        <SelectItem
                                            key={name}
                                            value={String(index + 1)}
                                        >
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <InputError message={form.errors.start_month} />
                        </div>
                    </div>

                    <Collapsible open={more} onOpenChange={setMore}>
                        <CollapsibleTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                data-testid="plan-item-more"
                            >
                                {more ? 'Fewer details' : 'More details'}
                            </Button>
                        </CollapsibleTrigger>

                        <CollapsibleContent className="mt-4 space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-item-frequency">
                                        How often
                                    </Label>

                                    <Select
                                        value={form.data.frequency}
                                        onValueChange={(value) =>
                                            form.setData('frequency', value)
                                        }
                                    >
                                        <SelectTrigger id="plan-item-frequency">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {FREQUENCIES.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError
                                        message={form.errors.frequency}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="plan-item-payment-day">
                                        Day of the month it is paid
                                    </Label>

                                    <Input
                                        id="plan-item-payment-day"
                                        name="payment_day"
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        max={31}
                                        placeholder="Any day"
                                        value={form.data.payment_day}
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_day',
                                                event.target.value,
                                            )
                                        }
                                    />

                                    <InputError
                                        message={form.errors.payment_day}
                                    />
                                </div>
                            </div>

                            {isIrregular && (
                                <div className="flex items-center gap-2">
                                    <Switch
                                        id="plan-item-allocation"
                                        checked={
                                            form.data.allocation === 'spread'
                                        }
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'allocation',
                                                checked ? 'spread' : 'lump_sum',
                                            )
                                        }
                                    />

                                    <Label
                                        htmlFor="plan-item-allocation"
                                        className="font-normal"
                                    >
                                        Set money aside for it every month
                                    </Label>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="plan-item-notes">Notes</Label>

                                <Input
                                    id="plan-item-notes"
                                    name="notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />

                                <InputError message={form.errors.notes} />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <div className="flex items-center gap-2">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-testid="save-plan-item"
                        >
                            {submitLabel}
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onDone}
                            data-testid="cancel-plan-item"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
