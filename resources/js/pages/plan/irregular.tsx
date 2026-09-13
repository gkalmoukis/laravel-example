import Money from '@/components/planning/money';
import { Badge } from '@/components/ui/badge';
import PlanLayout, { type PlanYear } from './layout';

type Item = {
    id: number;
    name: string;
    categoryName: string;
    annualCents: number;
    isSpread: boolean;
    startMonth: number;
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
    return (
        <PlanLayout
            year={year}
            tab={tab}
            tabs={tabs}
            title="Irregular"
            description="Costs that do not happen every month, like holidays or annual insurance."
        >
            <ul className="divide-y rounded-md border">
                {items.length === 0 && (
                    <li className="p-4 text-sm text-muted-foreground">
                        Nothing irregular planned yet.
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
                                {monthNames[item.startMonth - 1]}
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
