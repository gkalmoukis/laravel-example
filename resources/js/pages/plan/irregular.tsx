import GlossaryTerm from '@/components/finance/glossary-term';
import Money from '@/components/planning/money';
import { Badge } from '@/components/ui/badge';
import { usePreferences } from '@/hooks/use-preferences';
import PlanLayout, { type PlanYear } from './layout';

type Item = {
    id: number;
    name: string;
    categoryName: string;
    annualCents: number;
    isSpread: boolean;
    startMonth: number;
    dueOn: string | null;
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

export default function PlanIrregular({
    year,
    tab,
    tabs,
    items,
}: {
    year: PlanYear;
    tab: string;
    tabs: string[];
    items: Item[];
}) {
    const { formatDate } = usePreferences();

    return (
        <PlanLayout
            year={year}
            tab={tab}
            tabs={tabs}
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
        >
            <ul className="divide-y rounded-md border">
                {items.length === 0 && (
                    <li className="p-4 text-sm text-muted-foreground">
                        Nothing irregular planned yet — add a holiday, an annual
                        insurance or a tax bill so the year expects it.
                    </li>
                )}

                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex flex-wrap items-center justify-between gap-2 p-4"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{item.name}</span>

                            <span className="text-sm text-muted-foreground">
                                {item.categoryName} ·{' '}
                                {item.dueOn === null
                                    ? monthNames[item.startMonth - 1]
                                    : `due ${formatDate(item.dueOn)}`}
                            </span>

                            {item.isSpread && (
                                <Badge variant="outline">
                                    Set aside monthly
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
        </PlanLayout>
    );
}
