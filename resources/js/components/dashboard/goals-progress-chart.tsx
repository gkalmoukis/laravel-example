import ProgressBar from '@/components/finance/progress-bar';
import { Badge } from '@/components/ui/badge';
import { usePreferences } from '@/hooks/use-preferences';
import ChartCard, { ChartEmpty } from './chart-card';

export type GoalBar = {
    goalId: number;
    name: string;
    currentCents: number;
    targetCents: number;
    isOffTrack: boolean;
    isReached: boolean;
};

const TITLE = 'Goals';

/**
 * How far along each goal is (GOAL-02, DASH-04).
 *
 * Plain bars rather than a charting library: a row per goal with its own figures beside
 * it reads better than an axis, and works at 375 px where a chart would not.
 */
export default function GoalsProgressChart({ goals }: { goals: GoalBar[] }) {
    const { formatAmount, formatMoney } = usePreferences();

    if (goals.length === 0) {
        return <ChartEmpty title={TITLE} message="Nothing to aim at yet." />;
    }

    return (
        <ChartCard
            title={TITLE}
            caption="How far along each goal is"
            columns={['Goal', 'Saved', 'Target']}
            rows={goals.map((goal) => [
                goal.name,
                formatAmount(goal.currentCents),
                formatAmount(goal.targetCents),
            ])}
        >
            <ul className="space-y-4 py-2" data-testid="goal-bars">
                {goals.map((goal) => (
                    <li key={goal.goalId} className="space-y-1">
                        <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">
                                {goal.name}
                                {goal.isReached && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-2 align-middle"
                                    >
                                        Reached
                                    </Badge>
                                )}
                                {!goal.isReached && goal.isOffTrack && (
                                    <Badge
                                        variant="outline"
                                        className="ml-2 align-middle"
                                    >
                                        Behind
                                    </Badge>
                                )}
                            </span>

                            <span className="text-muted-foreground tabular-nums">
                                {formatMoney(goal.currentCents)} of{' '}
                                {formatMoney(goal.targetCents)}
                            </span>
                        </div>

                        <ProgressBar
                            current={goal.currentCents}
                            target={goal.targetCents}
                            label={`${goal.name} progress`}
                        />
                    </li>
                ))}
            </ul>
        </ChartCard>
    );
}
