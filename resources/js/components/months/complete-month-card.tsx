import { router, usePage } from '@inertiajs/react';
import { CircleCheck, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    destroy as reopenMonth,
    store as completeMonth,
} from '@/routes/month-completion';

/**
 * Finishing a month, and changing your mind (MON-04, MON-05).
 *
 * Completion is what makes a month final to the forecast, so the refusals are shown here
 * rather than as a toast that disappears: a month that cannot be finished says why, right
 * next to the button that would have finished it.
 */
export default function CompleteMonthCard({
    year,
    month,
    monthName,
    isComplete,
    needsConfirmation,
}: {
    year: number;
    month: number;
    monthName: string;
    isComplete: boolean;
    needsConfirmation: boolean;
}) {
    const { errors } = usePage().props;
    const [working, setWorking] = useState(false);

    const complete = (confirmed: boolean) => {
        setWorking(true);

        router.post(
            completeMonth.url({ year, month }),
            { confirmed },
            { preserveScroll: true, onFinish: () => setWorking(false) },
        );
    };

    const reopen = () => {
        setWorking(true);

        router.delete(reopenMonth.url({ year, month }), {
            preserveScroll: true,
            onFinish: () => setWorking(false),
        });
    };

    if (isComplete) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <CircleCheck
                            className="size-4 text-status-ok"
                            aria-hidden="true"
                        />
                        {monthName} is complete
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Its figures are final, so the forecast uses what really
                        happened rather than your plan. Reopening updates the
                        forecast straight away.
                    </p>
                    <Button
                        variant="secondary"
                        disabled={working}
                        onClick={reopen}
                        data-testid="reopen-month"
                    >
                        <RotateCcw className="size-4" />
                        Reopen {monthName}
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Finished with {monthName}?
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                    Marking it complete tells the forecast to use what really
                    happened for this month instead of your plan.
                </p>

                {errors.month && (
                    <p
                        className="text-sm text-status-over"
                        data-testid="complete-blocked"
                    >
                        {errors.month}
                    </p>
                )}

                {errors.confirmed ? (
                    <div
                        className="space-y-2 rounded-md border border-status-warning/40 p-3"
                        data-testid="confirm-early"
                    >
                        <p className="text-sm text-status-warning">
                            {errors.confirmed}
                        </p>
                        <Button
                            disabled={working}
                            onClick={() => complete(true)}
                            data-testid="confirm-complete-month"
                        >
                            Yes, mark {monthName} complete
                        </Button>
                    </div>
                ) : (
                    <Button
                        disabled={working}
                        onClick={() =>
                            complete(needsConfirmation ? false : true)
                        }
                        data-testid="complete-month"
                    >
                        <CircleCheck className="size-4" />
                        Mark {monthName} as complete
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
