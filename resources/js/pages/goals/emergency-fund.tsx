import { Link, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import ProgressBar from '@/components/finance/progress-bar';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePreferences } from '@/hooks/use-preferences';
import { formatAmount } from '@/lib/money';
import GoalsLayout from '@/pages/goals/layout';
import { update as saveEmergencyFund } from '@/routes/emergency-fund';
import { create as createYear } from '@/routes/financial-years';
import { show as monthShow } from '@/routes/months';

type Status = {
    essentialMonthlyCents: number;
    monthsOfCover: number;
    targetCents: number;
    targetIsCustom: boolean;
    currentCents: number;
    remainingCents: number;
    monthlyContributionCents: number;
    monthsToTarget: number | null;
    isReached: boolean;
    projectedYearEndCents: number;
    hasBeenStarted: boolean;
};

type CategoryChoice = { id: number; name: string; isEssential: boolean };

/**
 * When the fund is reached, said as a month rather than a count: "in 14 months" is
 * arithmetic the reader then has to do themselves.
 */
function reachedWhen(
    months: number | null,
    isReached: boolean,
    locale: string,
): string {
    if (isReached) {
        return 'Reached.';
    }

    if (months === null) {
        return 'Not reachable with the current plan.';
    }

    const when = new Date();
    when.setMonth(when.getMonth() + months);

    return `On track for ${new Intl.DateTimeFormat(locale, {
        month: 'long',
        year: 'numeric',
    }).format(when)}.`;
}

export default function EmergencyFund({
    hasYear,
    year,
    status,
    goal,
    categories,
}: {
    hasYear: boolean;
    year: number | null;
    status: Status | null;
    goal: {
        customTargetCents: number | null;
        monthlyContributionCents: number | null;
    } | null;
    categories: CategoryChoice[];
}) {
    const { formatMoney, formatLocale } = usePreferences();

    const form = useForm({
        months_of_cover: status?.monthsOfCover ?? 6,
        essential_category_ids: categories
            .filter((category) => category.isEssential)
            .map((category) => category.id),
        custom_target:
            goal?.customTargetCents === null ||
            goal?.customTargetCents === undefined
                ? ''
                : formatAmount(goal.customTargetCents, formatLocale),
        monthly_contribution:
            goal?.monthlyContributionCents === null ||
            goal?.monthlyContributionCents === undefined
                ? ''
                : formatAmount(goal.monthlyContributionCents, formatLocale),
    });

    if (!hasYear || !status) {
        return (
            <GoalsLayout
                tab="emergency-fund"
                title="Emergency fund"
                description="Money set aside in case your income stops."
            >
                <div
                    className="rounded-lg border border-dashed p-10 text-center"
                    data-testid="needs-year"
                >
                    <p className="text-muted-foreground">
                        Your target comes from what you have planned to spend,
                        so there is nothing to work it out from yet.
                    </p>
                    <Button className="mt-3" asChild>
                        <Link href={createYear()}>Create a plan</Link>
                    </Button>
                </div>
            </GoalsLayout>
        );
    }

    const toggle = (id: number, checked: boolean) => {
        form.setData(
            'essential_category_ids',
            checked
                ? [...form.data.essential_category_ids, id]
                : form.data.essential_category_ids.filter(
                      (value) => value !== id,
                  ),
        );
    };

    return (
        <GoalsLayout
            tab="emergency-fund"
            title="Emergency fund"
            description="Money set aside in case your income stops."
        >
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck
                                className="size-4 text-status-ok"
                                aria-hidden="true"
                            />
                            Where you are
                        </CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <div>
                            <p className="text-2xl font-semibold tabular-nums">
                                {formatMoney(status.currentCents)}{' '}
                                <span className="text-base font-normal text-muted-foreground">
                                    of {formatMoney(status.targetCents)}
                                </span>
                            </p>

                            <ProgressBar
                                className="mt-2"
                                current={status.currentCents}
                                target={status.targetCents}
                                label="Emergency fund progress"
                            />
                        </div>

                        <dl className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Still to go
                                </dt>
                                <dd className="mt-1 font-medium tabular-nums">
                                    {formatMoney(status.remainingCents)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    By the end of {year}
                                </dt>
                                <dd className="mt-1 font-medium tabular-nums">
                                    {formatMoney(status.projectedYearEndCents)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    When
                                </dt>
                                <dd
                                    className="mt-1 font-medium"
                                    data-testid="reached-when"
                                >
                                    {reachedWhen(
                                        status.monthsToTarget,
                                        status.isReached,
                                        formatLocale,
                                    )}
                                </dd>
                            </div>
                        </dl>

                        <p
                            className="text-sm text-muted-foreground"
                            data-testid="target-explanation"
                        >
                            {status.targetIsCustom
                                ? `You have set your own target of ${formatMoney(status.targetCents)}.`
                                : `${status.monthsOfCover} months × ${formatMoney(
                                      status.essentialMonthlyCents,
                                  )} of essential monthly expenses.`}
                        </p>

                        {!status.hasBeenStarted && (
                            <p className="text-sm" data-testid="not-started">
                                Tell us how much you already have set aside —
                                record it against your emergency fund in{' '}
                                <Link
                                    className="underline underline-offset-4"
                                    href={monthShow({
                                        year: year ?? 0,
                                        month: 1,
                                    })}
                                >
                                    a month review
                                </Link>
                                .
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            What you are aiming for
                        </CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="months_of_cover">
                                    Months of cover
                                </Label>
                                <Input
                                    id="months_of_cover"
                                    name="months_of_cover"
                                    type="number"
                                    min={1}
                                    max={24}
                                    className="mt-1"
                                    value={form.data.months_of_cover}
                                    onChange={(event) =>
                                        form.setData(
                                            'months_of_cover',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.months_of_cover}
                                />
                            </div>

                            <div>
                                <Label htmlFor="custom_target">
                                    Or your own target
                                </Label>
                                <Input
                                    id="custom_target"
                                    name="custom_target"
                                    inputMode="decimal"
                                    className="mt-1"
                                    placeholder="Leave empty to work it out"
                                    value={form.data.custom_target}
                                    onChange={(event) =>
                                        form.setData(
                                            'custom_target',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.custom_target}
                                />
                            </div>

                            <div>
                                <Label htmlFor="monthly_contribution">
                                    Putting aside each month
                                </Label>
                                <Input
                                    id="monthly_contribution"
                                    name="monthly_contribution"
                                    inputMode="decimal"
                                    className="mt-1"
                                    value={form.data.monthly_contribution}
                                    onChange={(event) =>
                                        form.setData(
                                            'monthly_contribution',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.monthly_contribution}
                                />
                            </div>
                        </div>

                        <fieldset>
                            <legend className="text-sm font-medium">
                                What you would still have to pay
                            </legend>
                            <p className="mt-1 text-sm text-muted-foreground">
                                These decide the target. Tick the costs that
                                would not stop if your income did.
                            </p>

                            <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {categories.map((category) => (
                                    <label
                                        key={category.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.essential_category_ids.includes(
                                                category.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    category.id,
                                                    checked === true,
                                                )
                                            }
                                            data-testid={`essential-${category.id}`}
                                        />
                                        {category.name}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <Button
                            disabled={form.processing}
                            onClick={() =>
                                form.patch(saveEmergencyFund.url(), {
                                    preserveScroll: true,
                                })
                            }
                            data-testid="save-emergency-fund"
                        >
                            Save
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </GoalsLayout>
    );
}
