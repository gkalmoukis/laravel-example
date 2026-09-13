import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, ShieldAlert } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export type AlertItem = {
    type: string;
    title: string;
    explanation: string;
    actionLabel: string;
    actionUrl: string;
    severity: number;
};

/**
 * What the year is trying to say, loudest first (§8.19, DASH-05).
 *
 * Each alert says what happened, why it matters and offers exactly one thing to do about
 * it — a list of choices would be a decision rather than a prompt. The panel disappears
 * entirely when there is nothing to say, rather than reassuring the user that all is well
 * in a box they have learned to ignore.
 */
export default function AlertsPanel({ alerts }: { alerts: AlertItem[] }) {
    if (alerts.length === 0) {
        return null;
    }

    return (
        <section aria-label="Alerts" data-testid="alerts-panel">
            <ul className="space-y-3">
                {alerts.map((alert) => (
                    <li key={alert.type}>
                        <Card
                            className={
                                alert.severity <= 1
                                    ? 'border-destructive/40'
                                    : undefined
                            }
                            data-testid={`alert-${alert.type}`}
                        >
                            <CardContent className="flex flex-wrap items-start gap-3">
                                <Severity severity={alert.severity} />

                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">{alert.title}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {alert.explanation}
                                    </p>
                                </div>

                                <Link
                                    href={alert.actionUrl}
                                    className="inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline"
                                >
                                    {alert.actionLabel}
                                    <ArrowRight
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </CardContent>
                        </Card>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * The two loudest alerts are about running out of money; the rest are about figures that
 * have drifted. Colour alone never carries that difference — the sentence does (NFR-06).
 */
function Severity({ severity }: { severity: number }) {
    const Icon = severity <= 1 ? ShieldAlert : AlertTriangle;

    return (
        <Icon
            className={
                severity <= 1
                    ? 'mt-0.5 size-5 shrink-0 text-destructive'
                    : 'mt-0.5 size-5 shrink-0 text-muted-foreground'
            }
            aria-hidden="true"
        />
    );
}
