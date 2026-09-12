import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/accounts';
import type { BreadcrumbItem } from '@/types';

type AccountType = 'cash' | 'bank' | 'card' | 'other';

type Account = {
    id: number;
    name: string;
    type: AccountType;
    isActive: boolean;
};

const typeLabels: Record<AccountType, string> = {
    cash: 'Cash',
    bank: 'Bank',
    card: 'Card',
    other: 'Other',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Accounts', href: index() }];

export default function Accounts({ accounts }: { accounts: Account[] }) {
    const [deactivating, setDeactivating] = useState<Account | null>(null);

    const active = accounts.filter((account) => account.isActive);
    const inactive = accounts.filter((account) => !account.isActive);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Accounts" />

            <h1 className="sr-only">Accounts</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Accounts"
                        description="Where your money sits. Used to label transactions, not to track balances."
                    />

                    <Form
                        {...AccountController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex flex-col gap-4 sm:flex-row sm:items-start"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        placeholder="Savings account"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2 sm:w-40">
                                    <Label htmlFor="type">Type</Label>

                                    <Select name="type" defaultValue="bank">
                                        <SelectTrigger id="type">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {Object.entries(typeLabels).map(
                                                ([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.type} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="sm:mt-8"
                                    data-test="add-account-button"
                                >
                                    Add account
                                </Button>
                            </>
                        )}
                    </Form>

                    <ul className="divide-y rounded-md border">
                        {accounts.length === 0 && (
                            <li className="p-4 text-sm text-muted-foreground">
                                No accounts yet.
                            </li>
                        )}

                        {[...active, ...inactive].map((account) => (
                            <li
                                key={account.id}
                                className="flex flex-wrap items-center justify-between gap-2 p-4"
                            >
                                <div>
                                    <span className="font-medium">
                                        {account.name}
                                    </span>

                                    <span className="ml-2 text-sm text-muted-foreground">
                                        {typeLabels[account.type]}
                                    </span>

                                    {!account.isActive && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            Inactive
                                        </Badge>
                                    )}
                                </div>

                                {account.isActive ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setDeactivating(account)}
                                    >
                                        Deactivate
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.patch(
                                                AccountController.update.url({
                                                    account: account.id,
                                                }),
                                                {
                                                    name: account.name,
                                                    type: account.type,
                                                    is_active: true,
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Reactivate
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            </SettingsLayout>

            <Dialog
                open={deactivating !== null}
                onOpenChange={(open) => !open && setDeactivating(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Deactivate {deactivating?.name}?
                        </DialogTitle>

                        <DialogDescription>
                            It will stop appearing when you record a
                            transaction. Anything already recorded against it
                            keeps showing it, and you can reactivate it at any
                            time.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="secondary"
                            onClick={() => setDeactivating(null)}
                        >
                            Keep it active
                        </Button>

                        <Button
                            variant="destructive"
                            data-test="confirm-deactivate-account-button"
                            onClick={() => {
                                if (deactivating) {
                                    router.delete(
                                        AccountController.destroy.url({
                                            account: deactivating.id,
                                        }),
                                        { preserveScroll: true },
                                    );
                                }

                                setDeactivating(null);
                            }}
                        >
                            Deactivate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
