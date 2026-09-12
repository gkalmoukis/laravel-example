import { router } from '@inertiajs/react';
import PlanBaselineController from '@/actions/App/Http/Controllers/PlanBaselineController';
import SalaryModelController from '@/actions/App/Http/Controllers/SalaryModelController';
import Money from '@/components/planning/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PlanLayout, { type PlanYear } from './layout';

type Item = {
    id: number;
    name: string;
    categoryName: string;
    annualCents: number;
    isManual: boolean;
    source: string;
};

type Props = {
    year: PlanYear;
    tab: string;
    tabs: string[];
    items: Item[];
    salaryModel: { baseAmountCents: number } | null;
    summary: Record<string, number | Record<number, number>>;
};

export default function PlanIncome({
    year,
    tab,
    tabs,
    items,
    salaryModel,
}: Props) {
    return (
        <PlanLayout
            year={year}
            tab={tab}
            tabs={tabs}
            title="Income"
            description="What you expect to earn this year."
        >
            <div className="space-y-6">
                {salaryModel && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Monthly net salary
                            </p>
                            <p className="text-lg font-medium">
                                <Money cents={salaryModel.baseAmountCents} />
                            </p>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.delete(
                                    SalaryModelController.destroy.url({
                                        year: year.year,
                                    }),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Remove and plan by hand
                        </Button>
                    </div>
                )}

                <ul className="divide-y rounded-md border">
                    {items.length === 0 && (
                        <li className="p-4 text-sm text-muted-foreground">
                            No income planned yet.
                        </li>
                    )}

                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-2 p-4"
                        >
                            <div className="flex items-center gap-2">
                                <span className="font-medium">{item.name}</span>

                                <span className="text-sm text-muted-foreground">
                                    {item.categoryName}
                                </span>

                                {!item.isManual && (
                                    <Badge variant="secondary">
                                        From your salary
                                    </Badge>
                                )}
                            </div>

                            <Money
                                cents={item.annualCents}
                                className="font-medium"
                            />
                        </li>
                    ))}
                </ul>

                <div className="flex flex-wrap items-center gap-3 border-t pt-6">
                    <Button
                        variant="outline"
                        size="sm"
                        data-test="reset-baseline-button"
                        onClick={() =>
                            router.post(
                                PlanBaselineController.store.url({
                                    year: year.year,
                                }),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Reset baseline to current plan
                    </Button>

                    <span className="text-sm text-muted-foreground">
                        {year.hasBaseline
                            ? 'Your original plan is saved, so drift is measured against it.'
                            : 'No original plan saved yet.'}
                    </span>
                </div>
            </div>
        </PlanLayout>
    );
}
