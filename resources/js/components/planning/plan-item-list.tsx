import { router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PlanItemController from '@/actions/App/Http/Controllers/PlanItemController';
import Money from '@/components/planning/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type PlanListItem = {
    id: number;
    name: string;
    annualCents: number;
    isManual: boolean;
    source: string;
};

/**
 * One row per planned item, with the two things that were missing until now: a way to
 * correct it and a way to remove it.
 *
 * An item the salary model or a subscription generated stays read-only — it is a
 * reflection of something owned elsewhere, and editing it here would be undone the next
 * time that thing is saved. `isManual` is what the server already says about this.
 */
export default function PlanItemList({
    year,
    items,
    empty,
    detail,
    onEdit,
}: {
    year: number;
    items: PlanListItem[];
    empty: string;
    detail: (item: PlanListItem) => React.ReactNode;
    onEdit: (item: PlanListItem) => void;
}) {
    const [confirming, setConfirming] = useState<PlanListItem | null>(null);

    const remove = (item: PlanListItem) => {
        router.delete(
            PlanItemController.destroy.url({ year, planItem: item.id }),
            {
                preserveScroll: true,
                onFinish: () => setConfirming(null),
            },
        );
    };

    return (
        <>
            <ul className="divide-y rounded-md border bg-card">
                {items.length === 0 && (
                    <li className="p-4 text-sm text-muted-foreground">
                        {empty}
                    </li>
                )}

                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex flex-wrap items-center justify-between gap-2 p-4"
                    >
                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                            <span className="font-medium">{item.name}</span>

                            {detail(item)}
                        </div>

                        <div className="flex items-center gap-2">
                            <Money
                                cents={item.annualCents}
                                className="font-medium tabular-nums"
                            />

                            {item.isManual ? (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onEdit(item)}
                                        aria-label={`Edit ${item.name}`}
                                        data-testid={`edit-plan-item-${item.id}`}
                                    >
                                        <Pencil className="size-4" />
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setConfirming(item)}
                                        aria-label={`Remove ${item.name}`}
                                        data-testid={`remove-plan-item-${item.id}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </>
                            ) : (
                                <Badge variant="outline">
                                    {item.source === 'salary_model'
                                        ? 'From your salary'
                                        : 'From a subscription'}
                                </Badge>
                            )}
                        </div>
                    </li>
                ))}
            </ul>

            <Dialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove {confirming?.name}?</DialogTitle>

                        <DialogDescription>
                            It comes out of the plan for every month of this
                            year. Transactions you have already recorded against
                            it are not touched.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirming(null)}
                        >
                            Keep it
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                confirming !== null && remove(confirming)
                            }
                            data-testid="confirm-remove-plan-item"
                        >
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
