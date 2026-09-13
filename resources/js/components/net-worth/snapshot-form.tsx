import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePreferences } from '@/hooks/use-preferences';
import { formatAmount } from '@/lib/money';
import { update as saveSnapshots } from '@/routes/net-worth-snapshots';

export type Holding = {
    id: number;
    name: string;
    kind: string;
    isLiquid: boolean;
    isDebt: boolean;
    valueCents: number;
};

/**
 * What each holding is worth at the end of the month (MON-06).
 *
 * This is the one thing the application cannot work out for itself: transactions say what
 * moved, but only the user knows what an investment is now worth. Saving is optional —
 * a month can be finished without it.
 */
export default function SnapshotForm({
    year,
    month,
    monthName,
    holdings,
    liquidClosingCents,
}: {
    year: number;
    month: number;
    monthName: string;
    holdings: Holding[];
    liquidClosingCents: number;
}) {
    const { formatMoney, formatLocale } = usePreferences();
    const { errors } = usePage().props;

    const [amounts, setAmounts] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            holdings.map((holding) => [
                holding.id,
                formatAmount(holding.valueCents, formatLocale),
            ]),
        ),
    );

    // The form is given its real shape up front. An empty useForm({}) has nothing for
    // transform() to build on, so the request goes out with no holdings at all.
    const form = useForm<{ holdings: { id: number; amount: string }[] }>({
        holdings: holdings.map((holding) => ({
            id: holding.id,
            amount: formatAmount(holding.valueCents, formatLocale),
        })),
    });

    const liquidTotal = holdings
        .filter((holding) => holding.isLiquid)
        .reduce((total, holding) => {
            const typed = Number(
                (amounts[holding.id] ?? '')
                    .replace(/\./g, '')
                    .replace(',', '.'),
            );

            return (
                total + (Number.isFinite(typed) ? Math.round(typed * 100) : 0)
            );
        }, 0);

    const difference = liquidTotal - liquidClosingCents;

    const submit = () => {
        form.transform(() => ({
            holdings: holdings.map((holding) => ({
                id: holding.id,
                amount: amounts[holding.id] ?? '0',
            })),
        }));

        form.patch(saveSnapshots.url({ year, month }), {
            preserveScroll: true,
            onSuccess: () => form.transform((data) => data),
        });
    };

    if (holdings.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    What you had at the end of {monthName}
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Optional, and prefilled with the last figures you gave.
                    Debts are recorded as what is still owed.
                </p>

                <div className="grid gap-3 sm:grid-cols-2">
                    {holdings.map((holding, index) => (
                        <div key={holding.id}>
                            <Label htmlFor={`holding-${holding.id}`}>
                                {holding.name}
                                {holding.isDebt && (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        still owed
                                    </span>
                                )}
                            </Label>
                            <Input
                                id={`holding-${holding.id}`}
                                name={`holdings[${index}][amount]`}
                                inputMode="decimal"
                                className="mt-1"
                                value={amounts[holding.id] ?? ''}
                                onChange={(event) =>
                                    setAmounts({
                                        ...amounts,
                                        [holding.id]: event.target.value,
                                    })
                                }
                            />
                            {errors[`holdings.${index}.amount`] && (
                                <p className="mt-1 text-xs text-status-over">
                                    {errors[`holdings.${index}.amount`]}
                                </p>
                            )}
                        </div>
                    ))}
                </div>

                <p
                    className="text-sm text-muted-foreground"
                    data-testid="liquid-hint"
                >
                    Your transactions add up to{' '}
                    {formatMoney(liquidClosingCents)} in cash and emergency fund
                    by the end of {monthName}.
                    {difference !== 0 && (
                        <span data-testid="liquid-difference">
                            {' '}
                            What you have typed is{' '}
                            {formatMoney(Math.abs(difference))}{' '}
                            {difference > 0 ? 'higher' : 'lower'}.
                        </span>
                    )}
                </p>

                <Button
                    disabled={form.processing}
                    onClick={submit}
                    data-testid="save-snapshots"
                >
                    Save what I have
                </Button>
            </CardContent>
        </Card>
    );
}
