import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type CategoryOption = { id: number; name: string; isDefault: boolean };
export type AccountOption = { id: number; name: string };
export type FrequencyOption = { value: string; label: string };

export type SubscriptionValues = {
    name: string;
    amount: string;
    frequency: string;
    billing_anchor_date: string;
    category_id: string;
    account_id: string;
    notes: string;
};

/**
 * The same fields whether something is being added or changed (SUB-01).
 *
 * Only one of these is ever on screen at a time, so the field ids stay fixed and a label
 * always points at the input the user is looking at.
 */
export default function SubscriptionForm({
    title,
    submitLabel,
    url,
    method,
    initial,
    categories,
    accounts,
    frequencies,
    onDone,
}: {
    title: string;
    submitLabel: string;
    url: string;
    method: 'post' | 'patch';
    initial: SubscriptionValues;
    categories: CategoryOption[];
    accounts: AccountOption[];
    frequencies: FrequencyOption[];
    onDone: () => void;
}) {
    const form = useForm<SubscriptionValues>(initial);

    const submit = () => {
        form.submit(method, url, {
            preserveScroll: true,
            onSuccess: () => {
                onDone();
            },
        });
    };

    return (
        <Card data-testid="subscription-form">
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            className="mt-1"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div>
                        <Label htmlFor="amount">Amount</Label>
                        <Input
                            id="amount"
                            name="amount"
                            inputMode="decimal"
                            className="mt-1"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                        <InputError message={form.errors.amount} />
                    </div>

                    <div>
                        <Label htmlFor="frequency">How often</Label>
                        <Select
                            value={form.data.frequency}
                            onValueChange={(value) =>
                                form.setData('frequency', value)
                            }
                        >
                            <SelectTrigger
                                id="frequency"
                                className="mt-1 w-full"
                                data-testid="subscription-frequency"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {frequencies.map((frequency) => (
                                    <SelectItem
                                        key={frequency.value}
                                        value={frequency.value}
                                    >
                                        {frequency.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.frequency} />
                    </div>

                    <div>
                        <Label htmlFor="billing_anchor_date">
                            A billing date
                        </Label>
                        <Input
                            id="billing_anchor_date"
                            name="billing_anchor_date"
                            type="date"
                            className="mt-1"
                            value={form.data.billing_anchor_date}
                            onChange={(event) =>
                                form.setData(
                                    'billing_anchor_date',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.billing_anchor_date} />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Any charge, past or future. The rest are worked out
                            from it.
                        </p>
                    </div>

                    <div>
                        <Label htmlFor="category_id">Category</Label>
                        <Select
                            value={form.data.category_id}
                            onValueChange={(value) =>
                                form.setData('category_id', value)
                            }
                        >
                            <SelectTrigger
                                id="category_id"
                                className="mt-1 w-full"
                                data-testid="subscription-category"
                            >
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
                        <p className="mt-1 text-xs text-muted-foreground">
                            A subcategory with this name is kept under it, so
                            the charges file themselves.
                        </p>
                    </div>

                    <div>
                        <Label htmlFor="account_id">Paid from</Label>
                        <Select
                            value={form.data.account_id}
                            onValueChange={(value) =>
                                form.setData('account_id', value)
                            }
                        >
                            <SelectTrigger
                                id="account_id"
                                className="mt-1 w-full"
                                data-testid="subscription-account"
                            >
                                <SelectValue placeholder="Not set" />
                            </SelectTrigger>
                            <SelectContent>
                                {accounts.map((account) => (
                                    <SelectItem
                                        key={account.id}
                                        value={String(account.id)}
                                    >
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.account_id} />
                    </div>

                    <div className="sm:col-span-2">
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
                </div>

                <div className="flex gap-2">
                    <Button
                        disabled={form.processing}
                        onClick={submit}
                        data-testid="save-subscription"
                    >
                        {submitLabel}
                    </Button>
                    <Button
                        variant="ghost"
                        onClick={onDone}
                        data-testid="cancel-subscription"
                    >
                        Cancel
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
