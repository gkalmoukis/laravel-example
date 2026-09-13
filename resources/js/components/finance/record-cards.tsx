import type { ReactNode } from 'react';

export type RecordCard = {
    key: string | number;
    title: ReactNode;
    fields: { label: string; value: ReactNode }[];
};

/**
 * What a wide table becomes on a phone (UX-13, NFR-06).
 *
 * Six columns at 375 px either scroll sideways or shrink until the figures are unreadable,
 * so each row becomes a card with its values labelled. The labels are repeated per card on
 * purpose: a column heading three screens up is no help to someone reading the fourth row.
 *
 * The budget grid is the one table that does not use this — twelve months of editable
 * cells becomes a month at a time instead (8.7).
 */
export default function RecordCards({
    items,
    testId,
}: {
    items: RecordCard[];
    testId?: string;
}) {
    return (
        <ul className="space-y-3" data-testid={testId ?? 'record-cards'}>
            {items.map((item) => (
                <li
                    key={item.key}
                    className="rounded-lg border p-3 text-sm"
                    data-testid={`record-card-${item.key}`}
                >
                    <p className="mb-2 font-medium">{item.title}</p>

                    <dl className="grid grid-cols-2 gap-x-3 gap-y-1">
                        {item.fields.map((field) => (
                            <div
                                key={field.label}
                                className="flex justify-between gap-2"
                            >
                                <dt className="text-muted-foreground">
                                    {field.label}
                                </dt>
                                <dd className="tabular-nums">{field.value}</dd>
                            </div>
                        ))}
                    </dl>
                </li>
            ))}
        </ul>
    );
}
