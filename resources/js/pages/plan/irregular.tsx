import { Plus } from 'lucide-react';
import { useState } from 'react';
import PlanItemController from '@/actions/App/Http/Controllers/PlanItemController';
import GlossaryTerm from '@/components/finance/glossary-term';
import PlanItemForm, {
    blankPlanItem,
    MONTH_NAMES,
    type PlanCategoryOption,
    type PlanItemValues,
} from '@/components/planning/plan-item-form';
import PlanItemList from '@/components/planning/plan-item-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePreferences } from '@/hooks/use-preferences';
import PlanLayout, { type PlanTotals, type PlanYear } from './layout';

type Item = {
    id: number;
    name: string;
    categoryId: number;
    categoryName: string;
    annualCents: number;
    isSpread: boolean;
    isManual: boolean;
    source: string;
    frequency: string;
    startMonth: number;
    paymentDay: number | null;
    allocation: string;
    months: Record<number, number>;
    dueOn: string | null;
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

export default function PlanIrregular({
    year,
    tab,
    tabs,
    planTotals,
    emptyTabs,
    items,
    categories,
}: {
    year: PlanYear;
    tab: string;
    tabs: string[];
    planTotals: PlanTotals;
    emptyTabs: string[];
    items: Item[];
    categories: PlanCategoryOption[];
}) {
    const { formatDate, formatAmount } = usePreferences();

    // 'new', the id being changed, or nothing open — one form at a time.
    const [open, setOpen] = useState<'new' | number | null>(null);

    const valuesOf = (item: Item): PlanItemValues => ({
        type: 'expense',
        kind: 'irregular',
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
            planTotals={planTotals}
            emptyTabs={emptyTabs}
            title="Irregular"
            description={
                <>
                    Every{' '}
                    <GlossaryTerm term="irregularExpense">
                        irregular expense
                    </GlossaryTerm>{' '}
                    the year expects.
                </>
            }
            action={
                <Button
                    onClick={() => setOpen(open === 'new' ? null : 'new')}
                    data-testid="add-irregular"
                >
                    <Plus className="size-4" />
                    New irregular cost
                </Button>
            }
        >
            <div className="space-y-6">
                {open === 'new' && (
                    <PlanItemForm
                        title="A new irregular cost"
                        submitLabel="Add to plan"
                        url={PlanItemController.store.url({ year: year.year })}
                        method="post"
                        initial={blankPlanItem('expense', 'irregular')}
                        categories={categories}
                        amountLabel="Amount for the year"
                        monthLabel="Month it is paid"
                        namePlaceholder="Summer holiday"
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
                        amountLabel="Amount for the year"
                        monthLabel="Month it is paid"
                        namePlaceholder="Summer holiday"
                        onDone={() => setOpen(null)}
                        onCancel={() => setOpen(null)}
                    />
                )}

                <PlanItemList
                    year={year.year}
                    items={items}
                    empty="Nothing irregular planned yet."
                    emptyHint="Add a holiday, an annual insurance or a tax bill so the year expects it."
                    onEdit={(item) => setOpen(item.id)}
                    detail={(item) => {
                        const row = items.find(
                            (candidate) => candidate.id === item.id,
                        );

                        if (row === undefined) {
                            return null;
                        }

                        return (
                            <>
                                <span className="text-sm text-muted-foreground">
                                    {row.categoryName} ·{' '}
                                    {row.dueOn === null
                                        ? MONTH_NAMES[row.startMonth - 1]
                                        : `due ${formatDate(row.dueOn)}`}
                                </span>

                                {row.isSpread && (
                                    <Badge variant="outline">
                                        Set aside monthly
                                    </Badge>
                                )}
                            </>
                        );
                    }}
                />
            </div>
        </PlanLayout>
    );
}
