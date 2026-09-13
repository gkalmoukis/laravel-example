import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import PlanBaselineController from '@/actions/App/Http/Controllers/PlanBaselineController';
import PlanItemController from '@/actions/App/Http/Controllers/PlanItemController';
import SalaryModelController from '@/actions/App/Http/Controllers/SalaryModelController';
import Money from '@/components/planning/money';
import PlanItemForm, {
    blankPlanItem,
    type PlanCategoryOption,
    type PlanItemValues,
} from '@/components/planning/plan-item-form';
import PlanItemList from '@/components/planning/plan-item-list';
import { Button } from '@/components/ui/button';
import { usePreferences } from '@/hooks/use-preferences';
import PlanLayout, { type PlanYear } from './layout';

type Item = {
    id: number;
    name: string;
    categoryId: number;
    categoryName: string;
    annualCents: number;
    isManual: boolean;
    source: string;
    frequency: string;
    startMonth: number;
    paymentDay: number | null;
    allocation: string;
    months: Record<number, number>;
};

type Props = {
    year: PlanYear;
    tab: string;
    tabs: string[];
    items: Item[];
    categories: PlanCategoryOption[];
    salaryModel: { baseAmountCents: number } | null;
    summary: Record<string, number | Record<number, number>>;
};

/**
 * The amount to put back in the form when an item is being changed.
 *
 * A spread item stores the year's total and lets `BuildPlanSchedule` divide it, so that
 * is what the field holds. Everything else stores the per-period amount, which is
 * exactly what landed in the starting month — read back as integer cents rather than
 * divided out of the annual figure.
 */
function periodAmountCents(item: {
    allocation: string;
    annualCents: number;
    startMonth: number;
    months: Record<number, number>;
}): number {
    if (item.allocation === 'spread') {
        return item.annualCents;
    }

    return item.months[item.startMonth] ?? 0;
}

export default function PlanIncome({
    year,
    tab,
    tabs,
    items,
    categories,
    salaryModel,
}: Props) {
    const { formatAmount } = usePreferences();

    // 'new', the id being changed, or nothing open — one form at a time.
    const [open, setOpen] = useState<'new' | number | null>(null);

    const valuesOf = (item: Item): PlanItemValues => ({
        type: 'income',
        kind: 'recurring',
        name: item.name,
        category_id: String(item.categoryId),
        amount: formatAmount(periodAmountCents(item)),
        frequency: item.frequency,
        start_month: String(item.startMonth),
        payment_day: item.paymentDay === null ? '' : String(item.paymentDay),
        allocation: item.allocation,
        notes: '',
    });

    const editing = items.find((item) => item.id === open);

    return (
        <PlanLayout
            year={year}
            tab={tab}
            tabs={tabs}
            title="Income"
            description="What you expect to earn this year."
            action={
                <Button
                    onClick={() => setOpen(open === 'new' ? null : 'new')}
                    data-testid="add-income"
                >
                    <Plus className="size-4" />
                    New income
                </Button>
            }
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

                {open === 'new' && (
                    <PlanItemForm
                        title="New income"
                        submitLabel="Add to plan"
                        url={PlanItemController.store.url({ year: year.year })}
                        method="post"
                        initial={blankPlanItem('income', 'recurring')}
                        categories={categories}
                        amountLabel="Amount each time"
                        monthLabel="Starting month"
                        namePlaceholder="Freelance work"
                        onDone={() => setOpen(null)}
                        onCancel={() => setOpen(null)}
                    />
                )}

                {editing && (
                    <PlanItemForm
                        title={`Change ${editing.name}`}
                        submitLabel="Save changes"
                        url={PlanItemController.update.url({
                            year: year.year,
                            planItem: editing.id,
                        })}
                        method="patch"
                        initial={valuesOf(editing)}
                        categories={categories}
                        amountLabel="Amount each time"
                        monthLabel="Starting month"
                        namePlaceholder="Freelance work"
                        onDone={() => setOpen(null)}
                        onCancel={() => setOpen(null)}
                    />
                )}

                <PlanItemList
                    year={year.year}
                    items={items}
                    empty="No income planned yet."
                    onEdit={(item) => setOpen(item.id)}
                    detail={(item) => {
                        const row = items.find(
                            (candidate) => candidate.id === item.id,
                        );

                        return (
                            <span className="text-sm text-muted-foreground">
                                {row?.categoryName}
                            </span>
                        );
                    }}
                />

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
