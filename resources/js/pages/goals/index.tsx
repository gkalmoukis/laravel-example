import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Target } from 'lucide-react';
import { useState } from 'react';
import GoalCard, { type GoalCardData } from '@/components/goals/goal-card';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { destroy as restoreGoal } from '@/routes/goal-archive';
import { index as goalsIndex, store as storeGoal } from '@/routes/goals';
import type { BreadcrumbItem } from '@/types';

type ArchivedGoal = { id: number; name: string; type: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Goals', href: goalsIndex() }];

const TYPES = [
    { value: 'investment', label: 'Investment' },
    { value: 'year_end_balance', label: 'Balance at year end' },
    { value: 'purchase', label: 'Something to buy' },
    { value: 'debt_payoff', label: 'Paying off a debt' },
    { value: 'other', label: 'Something else' },
];

export default function GoalsIndex({
    goals,
    archived,
    archivedCount,
    showArchived,
    years,
}: {
    goals: GoalCardData[];
    archived: ArchivedGoal[];
    archivedCount: number;
    showArchived: boolean;
    years: { id: number; year: number }[];
}) {
    const [adding, setAdding] = useState(false);

    const form = useForm({
        name: '',
        type: 'investment',
        target_amount: '',
        current_amount: '',
        monthly_contribution: '',
        target_date: '',
        financial_year_id: '',
    });

    const add = () => {
        form.post(storeGoal.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Goals" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Goals"
                        description="What you are putting money aside for."
                    />

                    <Button
                        onClick={() => setAdding(!adding)}
                        data-testid="add-goal"
                    >
                        <Plus className="size-4" />
                        New goal
                    </Button>
                </div>

                {adding && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                A new goal
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        className="mt-1"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div>
                                    <Label htmlFor="type">Kind</Label>
                                    <Select
                                        value={form.data.type}
                                        onValueChange={(value) =>
                                            form.setData('type', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="type"
                                            className="mt-1 w-full"
                                            data-testid="goal-type"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {TYPES.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label htmlFor="target_amount">
                                        Target
                                    </Label>
                                    <Input
                                        id="target_amount"
                                        name="target_amount"
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

                                <div>
                                    <Label htmlFor="monthly_contribution">
                                        Each month
                                    </Label>
                                    <Input
                                        id="monthly_contribution"
                                        name="monthly_contribution"
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
                                        message={
                                            form.errors.monthly_contribution
                                        }
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="target_date">
                                        Wanted by
                                    </Label>
                                    <Input
                                        id="target_date"
                                        name="target_date"
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

                                {form.data.type === 'year_end_balance' && (
                                    <div>
                                        <Label htmlFor="financial_year_id">
                                            Which year
                                        </Label>
                                        <Select
                                            value={form.data.financial_year_id}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'financial_year_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="financial_year_id"
                                                className="mt-1 w-full"
                                            >
                                                <SelectValue placeholder="Pick a year" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {years.map((year) => (
                                                    <SelectItem
                                                        key={year.id}
                                                        value={String(year.id)}
                                                    >
                                                        {year.year}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                form.errors.financial_year_id
                                            }
                                        />
                                    </div>
                                )}
                            </div>

                            <Button
                                disabled={form.processing}
                                onClick={add}
                                data-testid="save-goal"
                            >
                                Add goal
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {goals.length === 0 ? (
                    <div
                        className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-10 text-center"
                        data-testid="no-goals"
                    >
                        <Target
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-muted-foreground">
                            Nothing to aim at yet.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {goals.map((goal) => (
                            <GoalCard key={goal.id} goal={goal} />
                        ))}
                    </div>
                )}

                {archivedCount > 0 && (
                    <div className="space-y-3">
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={goalsIndex.url({
                                    query: showArchived
                                        ? {}
                                        : { archived: '1' },
                                })}
                                data-testid="toggle-archived"
                            >
                                {showArchived ? 'Hide' : 'Show'} {archivedCount}{' '}
                                archived
                            </Link>
                        </Button>

                        {showArchived && (
                            <ul
                                className="space-y-2"
                                data-testid="archived-list"
                            >
                                {archived.map((goal) => (
                                    <li
                                        key={goal.id}
                                        className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                    >
                                        {goal.name}
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    restoreGoal.url(goal.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                            data-testid={`restore-${goal.id}`}
                                        >
                                            Bring back
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
