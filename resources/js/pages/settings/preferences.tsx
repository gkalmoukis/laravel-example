import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import PreferencesController from '@/actions/App/Http/Controllers/PreferencesController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { edit } from '@/routes/preferences';
import type { BreadcrumbItem } from '@/types';

type Preferences = {
    currency: string;
    formatLocale: string;
    timezone: string;
    salaryPayments: number;
    defaultAccountId: number | null;
    emergencyFundMonths: number;
    budgetWarningThresholdPercent: number;
};

type Props = {
    settings: Preferences;
    formatLocales: string[];
    timezones: string[];
    accounts: { id: number; name: string }[];
};

const localeLabels: Record<string, string> = {
    'el-GR': 'Greek (Greece)',
    'en-GB': 'English (United Kingdom)',
    'en-US': 'English (United States)',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Preferences', href: edit() }];

const NO_DEFAULT_ACCOUNT = 'none';

export default function Preferences({
    settings,
    formatLocales,
    timezones,
    accounts,
}: Props) {
    // Previewed live so the effect of the choice is visible before saving.
    const [locale, setLocale] = useState(settings.formatLocale);
    const [months, setMonths] = useState(settings.emergencyFundMonths);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Preferences" />

            <h1 className="sr-only">Preferences</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Preferences"
                        description="How amounts and dates are shown, and the defaults used across the app"
                    />

                    <Form
                        {...PreferencesController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="format_locale">
                                        Number and date format
                                    </Label>

                                    <Select
                                        name="format_locale"
                                        value={locale}
                                        onValueChange={setLocale}
                                    >
                                        <SelectTrigger id="format_locale">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {formatLocales.map((value) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {localeLabels[value] ??
                                                        value}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <p
                                        className="text-sm text-muted-foreground"
                                        data-test="format-preview"
                                    >
                                        Amounts look like{' '}
                                        <span className="font-medium text-foreground">
                                            {formatMoney(
                                                123456,
                                                locale,
                                                settings.currency,
                                            )}
                                        </span>{' '}
                                        and dates like{' '}
                                        <span className="font-medium text-foreground">
                                            {formatDate('2027-12-31', locale)}
                                        </span>
                                        .
                                    </p>

                                    <InputError
                                        message={errors.format_locale}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">Timezone</Label>

                                    <Select
                                        name="timezone"
                                        defaultValue={settings.timezone}
                                    >
                                        <SelectTrigger id="timezone">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {timezones.map((zone) => (
                                                <SelectItem
                                                    key={zone}
                                                    value={zone}
                                                >
                                                    {zone}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <p className="text-sm text-muted-foreground">
                                        Used to decide what counts as today when
                                        you record something.
                                    </p>

                                    <InputError message={errors.timezone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="salary_payments">
                                        Salary payments per year
                                    </Label>

                                    <Select
                                        name="salary_payments"
                                        defaultValue={String(
                                            settings.salaryPayments,
                                        )}
                                    >
                                        <SelectTrigger id="salary_payments">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            <SelectItem value="12">
                                                12
                                            </SelectItem>
                                            <SelectItem value="14">
                                                14 (with bonuses)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <InputError
                                        message={errors.salary_payments}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="default_account_id">
                                        Default account
                                    </Label>

                                    <Select
                                        name="default_account_id"
                                        defaultValue={
                                            settings.defaultAccountId
                                                ? String(
                                                      settings.defaultAccountId,
                                                  )
                                                : NO_DEFAULT_ACCOUNT
                                        }
                                    >
                                        <SelectTrigger id="default_account_id">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            <SelectItem
                                                value={NO_DEFAULT_ACCOUNT}
                                            >
                                                No default
                                            </SelectItem>

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

                                    <InputError
                                        message={errors.default_account_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="emergency_fund_months">
                                        Emergency fund coverage
                                    </Label>

                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant={
                                                months === 3
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() => setMonths(3)}
                                        >
                                            3 months
                                        </Button>

                                        <Button
                                            type="button"
                                            variant={
                                                months === 6
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() => setMonths(6)}
                                        >
                                            6 months
                                        </Button>

                                        <Input
                                            id="emergency_fund_months"
                                            name="emergency_fund_months"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={24}
                                            required
                                            className="w-24"
                                            value={months}
                                            onChange={(event) =>
                                                setMonths(
                                                    Number(event.target.value),
                                                )
                                            }
                                        />

                                        <span className="text-sm text-muted-foreground">
                                            months of essential expenses
                                        </span>
                                    </div>

                                    <InputError
                                        message={errors.emergency_fund_months}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="budget_warning_threshold_percent">
                                        Budget warning threshold
                                    </Label>

                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="budget_warning_threshold_percent"
                                            name="budget_warning_threshold_percent"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={100}
                                            required
                                            className="w-24"
                                            defaultValue={
                                                settings.budgetWarningThresholdPercent
                                            }
                                        />

                                        <span className="text-sm text-muted-foreground">
                                            % over plan before a category is
                                            flagged
                                        </span>
                                    </div>

                                    <InputError
                                        message={
                                            errors.budget_warning_threshold_percent
                                        }
                                    />
                                </div>

                                <div className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <span className="text-sm font-medium">
                                            Currency
                                        </span>

                                        <span className="text-sm text-muted-foreground">
                                            {settings.currency}
                                        </span>
                                    </div>

                                    <div className="grid gap-1">
                                        <span className="text-sm font-medium">
                                            Financial year starts
                                        </span>

                                        <span className="text-sm text-muted-foreground">
                                            January
                                        </span>
                                    </div>

                                    <p className="text-sm text-muted-foreground sm:col-span-2">
                                        More options coming later.
                                    </p>
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="save-preferences-button"
                                    >
                                        Save preferences
                                    </Button>

                                    {recentlySuccessful && (
                                        <span className="text-sm text-muted-foreground">
                                            Saved.
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
