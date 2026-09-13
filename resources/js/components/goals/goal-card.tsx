import { router, useForm } from '@inertiajs/react';
import { Archive, CircleCheck, Lock, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import ProgressBar from '@/components/finance/progress-bar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePreferences } from '@/hooks/use-preferences';
import { formatAmount } from '@/lib/money';
import { store as archiveGoal } from '@/routes/goal-archive';
import { update as updateGoal } from '@/routes/goals';

export type GoalCardData = {
    id: number;
    name: string;
    type: string;
    targetCents: number;
    currentCents: number;
    remainingCents: number;
    monthlyContributionCents: number;
    targetDate: string | null;
    estimatedCompletion: string | null;
    isReached: boolean;
    isOffTrack: boolean;
    isTracked: boolean;
    canArchive: boolean;
};

/**
 * Where the current amount comes from, said plainly.
 *
 * A read-only figure with no explanation reads as a broken input, so each tracked type
 * says what keeps it up to date (GOAL-03).
 */
const TRACKED_BY: Record<string, string> = {
    emergency_fund: 'From what you have set aside.',
    year_end_balance: 'From your forecast for the year.',
};

export default function GoalCard({ goal }: { goal: GoalCardData }) {
    const { formatMoney, formatDate, formatLocale } = usePreferences();
    const [editing, setEditing] = useState(false);

    const form = useForm({
        name: goal.name,
        target_amount: formatAmount(goal.targetCents, formatLocale),
        current_amount: formatAmount(goal.currentCents, formatLocale),
        monthly_contribution: formatAmount(
            goal.monthlyContributionCents,
            formatLocale,
        ),
        target_date: goal.targetDate ?? '',
    });

    const save = () => {
        form.patch(updateGoal.url(goal.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <Card data-testid={`goal-${goal.id}`}>
            <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                <CardTitle className="text-base">{goal.name}</CardTitle>

                <div className="flex items-center gap-1">
                    {goal.isReached && (
                        <Badge
                            variant="outline"
                            className="gap-1 border-status-ok/40 text-status-ok"
                        >
                            <CircleCheck
                                className="size-3"
                                aria-hidden="true"
                            />
                            Reached
                        </Badge>
                    )}

                    {goal.isOffTrack && (
                        <Badge
                            variant="outline"
                            className="gap-1 border-status-over/40 text-status-over"
                            data-testid={`off-track-${goal.id}`}
                        >
                            <TriangleAlert
                                className="size-3"
                                aria-hidden="true"
                            />
                            Off track
                        </Badge>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-3">
                <div>
                    <p className="text-xl font-semibold tabular-nums">
                        {formatMoney(goal.currentCents)}{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            of {formatMoney(goal.targetCents)}
                        </span>
                    </p>

                    <ProgressBar
                        className="mt-2"
                        current={goal.currentCents}
                        target={goal.targetCents}
                        label={`${goal.name} progress`}
                    />
                </div>

                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Still to go</dt>
                        <dd className="tabular-nums">
                            {formatMoney(goal.remainingCents)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Expected</dt>
                        <dd data-testid={`expected-${goal.id}`}>
                            {goal.estimatedCompletion
                                ? formatDate(goal.estimatedCompletion)
                                : 'Not on this plan'}
                        </dd>
                    </div>
                    {goal.targetDate && (
                        <div>
                            <dt className="text-muted-foreground">Wanted by</dt>
                            <dd>{formatDate(goal.targetDate)}</dd>
                        </div>
                    )}
                    <div>
                        <dt className="text-muted-foreground">Each month</dt>
                        <dd className="tabular-nums">
                            {formatMoney(goal.monthlyContributionCents)}
                        </dd>
                    </div>
                </dl>

                {goal.isTracked && (
                    <p className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Lock className="size-3" aria-hidden="true" />
                        {TRACKED_BY[goal.type] ?? 'Kept up to date for you.'}
                    </p>
                )}

                {editing ? (
                    <div className="space-y-3 border-t pt-3">
                        <div>
                            <Label htmlFor={`name-${goal.id}`}>Name</Label>
                            <Input
                                id={`name-${goal.id}`}
                                className="mt-1"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label htmlFor={`target-${goal.id}`}>
                                    Target
                                </Label>
                                <Input
                                    id={`target-${goal.id}`}
                                    inputMode="decimal"
                                    className="mt-1"
                                    value={form.data.target_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'target_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.target_amount}
                                />
                            </div>

                            {!goal.isTracked && (
                                <div>
                                    <Label htmlFor={`current-${goal.id}`}>
                                        Saved so far
                                    </Label>
                                    <Input
                                        id={`current-${goal.id}`}
                                        inputMode="decimal"
                                        className="mt-1"
                                        value={form.data.current_amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'current_amount',
                                                event.target.value,
                                            )
                                        }
                                        data-testid={`current-input-${goal.id}`}
                                    />
                                    <InputError
                                        message={form.errors.current_amount}
                                    />
                                </div>
                            )}

                            <div>
                                <Label htmlFor={`contribution-${goal.id}`}>
                                    Each month
                                </Label>
                                <Input
                                    id={`contribution-${goal.id}`}
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

                            <div>
                                <Label htmlFor={`date-${goal.id}`}>
                                    Wanted by
                                </Label>
                                <Input
                                    id={`date-${goal.id}`}
                                    type="date"
                                    className="mt-1"
                                    value={form.data.target_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'target_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                disabled={form.processing}
                                onClick={save}
                                data-testid={`save-${goal.id}`}
                            >
                                Save
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setEditing(false)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => setEditing(true)}
                            data-testid={`edit-${goal.id}`}
                        >
                            Update
                        </Button>

                        {goal.canArchive && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        archiveGoal.url(goal.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                data-testid={`archive-${goal.id}`}
                            >
                                <Archive className="size-4" />
                                Archive
                            </Button>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
