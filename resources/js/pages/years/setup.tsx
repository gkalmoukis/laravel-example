import { Form, Head, Link, router } from '@inertiajs/react';
import OpeningPositionController from '@/actions/App/Http/Controllers/OpeningPositionController';
import PlanItemController from '@/actions/App/Http/Controllers/PlanItemController';
import SalaryModelController from '@/actions/App/Http/Controllers/SalaryModelController';
import YearSetupCompletionController from '@/actions/App/Http/Controllers/YearSetupCompletionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PageShell from '@/components/page-shell';
import Money from '@/components/planning/money';
import MoneyInput from '@/components/planning/money-input';
import PlanItemForm, {
    blankPlanItem,
} from '@/components/planning/plan-item-form';
import YearNav from '@/components/planning/year-nav';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Holding = {
    id: number;
    name: string;
    kind: string;
    valueCents: number;
};

type PlanItem = {
    id: number;
    name: string;
    type: string;
    kind: string;
    categoryName: string;
    annualCents: number;
    isManual: boolean;
};

type Category = { id: number; name: string; isIrregular?: boolean };

type Payment = {
    enabled: boolean;
    month: number;
    mode: string;
    multiplier: string;
    amount_cents: number;
};

type Props = {
    step: string;
    steps: string[];
    completedSteps: string[];
    year: { year: number; isSetupComplete: boolean };
    holdings?: Holding[];
    openingLiquidCents?: number;
    openingNetWorthCents?: number;
    planItems?: PlanItem[];
    categories?: Category[];
    salaryModel?: {
        name: string;
        baseAmountCents: number;
        payments: Record<string, Payment>;
    } | null;
    defaultPayments?: Record<string, Payment>;
    salaryPayments?: number;
};

const stepLabels: Record<string, string> = {
    opening: 'Opening position',
    income: 'Income',
    expenses: 'Monthly budget',
};

const bonusLabels: Record<string, string> = {
    christmas_bonus: 'Christmas bonus',
    easter_bonus: 'Easter bonus',
    vacation_allowance: 'Vacation allowance',
};

const monthNames = [
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

export default function YearSetup(props: Props) {
    const { step, steps, completedSteps, year } = props;

    const position = steps.indexOf(step);
    const nextStep = steps[position + 1];
    const previousStep = steps[position - 1];

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: `Set up ${year.year}`,
            href: `/years/${year.year}/setup/opening`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Set up ${year.year}`} />

            <PageShell stacked={false} className="mx-auto w-full max-w-3xl">
                <Heading
                    title={`Set up ${year.year}`}
                    description="Three short steps to get the year going. Everything can be changed afterwards from the plan, and you can skip anything except the first."
                />

                <div className="mt-4">
                    <YearNav
                        current={step}
                        steps={steps.map((key) => ({
                            key,
                            label: stepLabels[key] ?? key,
                            href: `/years/${year.year}/setup/${key}`,
                            done: completedSteps.includes(key),
                        }))}
                    />
                </div>

                <div className="mt-8 space-y-6">
                    {step === 'opening' && <OpeningStep {...props} />}
                    {step === 'income' && <IncomeStep {...props} />}
                    {step === 'expenses' && <PlanStep {...props} />}
                </div>

                {/*
                 * The quick-add button floats over the bottom-right corner on a phone,
                 * which is exactly where Next sits. The extra space below puts the
                 * wizard's own navigation clear of it (UX-13, NFR-06).
                 */}
                <div className="mt-10 flex items-center justify-between gap-4 border-t pt-6 pb-24 md:pb-0">
                    {previousStep ? (
                        <Button variant="ghost" asChild>
                            <Link
                                href={`/years/${year.year}/setup/${previousStep}`}
                            >
                                Back
                            </Link>
                        </Button>
                    ) : (
                        <span />
                    )}

                    {nextStep ? (
                        <Button asChild data-test="next-step-button">
                            <Link
                                href={`/years/${year.year}/setup/${nextStep}`}
                            >
                                {completedSteps.includes(step)
                                    ? 'Next'
                                    : 'Skip for now'}
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            data-test="finish-setup-button"
                            onClick={() =>
                                router.post(
                                    YearSetupCompletionController.store.url({
                                        year: year.year,
                                    }),
                                )
                            }
                        >
                            Finish setup
                        </Button>
                    )}
                </div>
            </PageShell>
        </AppLayout>
    );
}

function OpeningStep({
    year,
    holdings = [],
    openingLiquidCents = 0,
    openingNetWorthCents = 0,
}: Props) {
    return (
        <Form
            {...OpeningPositionController.update.form({ year: year.year })}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <p className="text-sm text-muted-foreground">
                        Where you start the year. Type what you have now — one
                        or two numbers is enough to begin.
                    </p>

                    <div className="space-y-4">
                        {holdings.map((holding, index) => (
                            <div key={holding.id} className="grid gap-2">
                                <Label htmlFor={`holding-${holding.id}`}>
                                    {holding.name}
                                </Label>

                                <input
                                    type="hidden"
                                    name={`holdings[${index}][id]`}
                                    value={holding.id}
                                />

                                <MoneyInput
                                    id={`holding-${holding.id}`}
                                    name={`holdings[${index}][amount]`}
                                    defaultCents={holding.valueCents}
                                    className="w-full sm:w-56"
                                />

                                <InputError
                                    message={
                                        errors[
                                            `holdings.${index}.amount` as keyof typeof errors
                                        ]
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    <dl className="grid gap-2 rounded-md border p-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Money you can spend
                            </dt>
                            <dd className="text-lg font-medium">
                                <Money cents={openingLiquidCents} />
                            </dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Net worth
                            </dt>
                            <dd className="text-lg font-medium">
                                <Money cents={openingNetWorthCents} />
                            </dd>
                        </div>
                    </dl>

                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="save-opening-button"
                    >
                        Save opening position
                    </Button>
                </>
            )}
        </Form>
    );
}

function IncomeStep({
    year,
    salaryModel,
    defaultPayments = {},
    salaryPayments = 14,
    planItems = [],
}: Props) {
    const payments = salaryModel?.payments ?? defaultPayments;
    const fourteen = salaryPayments === 14;

    return (
        <div className="space-y-8">
            <Form
                {...SalaryModelController.update.form({ year: year.year })}
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="base_amount">
                                Monthly net salary
                            </Label>

                            <MoneyInput
                                name="base_amount"
                                defaultCents={salaryModel?.baseAmountCents}
                                required
                                className="w-full sm:w-56"
                            />

                            <p className="text-sm text-muted-foreground">
                                Enter the amount that actually reaches your bank
                                account. Bonuses may be taxed differently;
                                adjust their amounts if needed.
                            </p>

                            <InputError message={errors.base_amount} />
                        </div>

                        <fieldset className="space-y-4">
                            <legend className="text-sm font-medium">
                                Extra payments
                                {fourteen && ' (the 14-payment model)'}
                            </legend>

                            {Object.entries(payments).map(([key, payment]) => (
                                <div
                                    key={key}
                                    className="flex flex-wrap items-center gap-3 rounded-md border p-3"
                                >
                                    <Switch
                                        id={`payment-${key}`}
                                        name={`payments[${key}][enabled]`}
                                        defaultChecked={payment.enabled}
                                    />

                                    <Label
                                        htmlFor={`payment-${key}`}
                                        className="flex-1 font-normal"
                                    >
                                        {bonusLabels[key] ?? key}
                                    </Label>

                                    <input
                                        type="hidden"
                                        name={`payments[${key}][mode]`}
                                        value={payment.mode}
                                    />
                                    <input
                                        type="hidden"
                                        name={`payments[${key}][multiplier]`}
                                        value={payment.multiplier}
                                    />
                                    <input
                                        type="hidden"
                                        name={`payments[${key}][amount_cents]`}
                                        value={payment.amount_cents}
                                    />

                                    <Select
                                        name={`payments[${key}][month]`}
                                        defaultValue={String(payment.month)}
                                    >
                                        <SelectTrigger className="w-40">
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {monthNames.map((name, index) => (
                                                <SelectItem
                                                    key={name}
                                                    value={String(index + 1)}
                                                >
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ))}
                        </fieldset>

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="save-salary-button"
                        >
                            Save salary
                        </Button>
                    </>
                )}
            </Form>

            <PlannedList
                items={planItems.filter((item) => item.type === 'income')}
                empty="No income planned yet."
            />
        </div>
    );
}

/**
 * The monthly budget, built from the same form the plan tab uses.
 *
 * This step used to carry its own copy of the whole plan-item form, which is how the
 * wizard and the plan drifted apart in the first place.
 */
function PlanStep({ year, planItems = [], categories = [] }: Props) {
    const recurring = planItems.filter((item) => item.kind === 'recurring');

    return (
        <div className="space-y-8">
            <PlanItemForm
                title="A monthly cost"
                submitLabel="Add to plan"
                url={PlanItemController.store.url({ year: year.year })}
                method="post"
                initial={blankPlanItem('expense', 'recurring')}
                categories={categories}
                amountLabel="Amount each month"
                monthLabel="Starting month"
                namePlaceholder="Rent"
                onDone={() => undefined}
            />

            <PlannedList
                items={recurring}
                empty="No monthly costs planned yet."
            />
        </div>
    );
}

function PlannedList({ items, empty }: { items: PlanItem[]; empty: string }) {
    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <ul className="divide-y rounded-md border">
            {items.map((item) => (
                <li
                    key={item.id}
                    className="flex flex-wrap items-center justify-between gap-2 p-4"
                >
                    <div>
                        <span className="font-medium">{item.name}</span>
                        <span className="ml-2 text-sm text-muted-foreground">
                            {item.categoryName}
                        </span>
                    </div>

                    <Money cents={item.annualCents} className="font-medium" />
                </li>
            ))}
        </ul>
    );
}
