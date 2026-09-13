import { Form } from '@inertiajs/react';
import OpeningPositionController from '@/actions/App/Http/Controllers/OpeningPositionController';
import InputError from '@/components/input-error';
import Money from '@/components/planning/money';
import MoneyInput from '@/components/planning/money-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import PlanLayout, { type PlanTotals, type PlanYear } from './layout';

type Holding = {
    id: number;
    name: string;
    kind: string;
    valueCents: number;
};

export default function PlanOpening({
    year,
    tab,
    tabs,
    planTotals,
    emptyTabs,
    holdings,
    openingLiquidCents,
    openingNetWorthCents,
}: {
    year: PlanYear;
    tab: string;
    tabs: string[];
    planTotals: PlanTotals;
    emptyTabs: string[];
    holdings: Holding[];
    openingLiquidCents: number;
    openingNetWorthCents: number;
}) {
    return (
        <PlanLayout
            year={year}
            tab={tab}
            tabs={tabs}
            planTotals={planTotals}
            emptyTabs={emptyTabs}
            title="Opening position"
            description={`Where ${year.year} starts. Everything else is measured from here.`}
        >
            <Form
                {...OpeningPositionController.update.form({ year: year.year })}
                options={{ preserveScroll: true }}
                className="max-w-xl space-y-6"
            >
                {({ processing, errors }) => (
                    <>
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
        </PlanLayout>
    );
}
