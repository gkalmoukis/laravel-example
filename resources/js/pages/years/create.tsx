import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import FinancialYearController from '@/actions/App/Http/Controllers/FinancialYearController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PageShell from '@/components/page-shell';
import { Button } from '@/components/ui/button';
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
import type { BreadcrumbItem } from '@/types';

type Props = {
    suggestedYear: number;
    earliestYear: number;
    latestYear: number;
    copyableYears: { id: number; year: number }[];
    takenYears: number[];
};

const START_EMPTY = 'empty';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'New plan', href: '/years/create' },
];

export default function CreateYear({
    suggestedYear,
    earliestYear,
    latestYear,
    copyableYears,
}: Props) {
    const [source, setSource] = useState(START_EMPTY);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Start a plan" />

            <PageShell stacked={false} className="mx-auto w-full max-w-2xl">
                <Heading
                    title="Start a plan"
                    description="Pick the year you want to plan for. You can change everything afterwards."
                />

                <Form
                    {...FinancialYearController.store.form()}
                    className="mt-6 space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="year">Year</Label>

                                <Input
                                    id="year"
                                    name="year"
                                    type="number"
                                    inputMode="numeric"
                                    required
                                    min={earliestYear}
                                    max={latestYear}
                                    defaultValue={suggestedYear}
                                    className="w-40"
                                />

                                <InputError message={errors.year} />
                            </div>

                            {copyableYears.length > 0 && (
                                <div className="grid gap-2">
                                    <Label htmlFor="copy_from_id">
                                        Start from
                                    </Label>

                                    <Select
                                        name="copy_from_id"
                                        value={source}
                                        onValueChange={setSource}
                                    >
                                        <SelectTrigger
                                            id="copy_from_id"
                                            className="w-full sm:w-80"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>

                                        <SelectContent>
                                            <SelectItem value={START_EMPTY}>
                                                An empty plan
                                            </SelectItem>

                                            {copyableYears.map((year) => (
                                                <SelectItem
                                                    key={year.id}
                                                    value={String(year.id)}
                                                >
                                                    A copy of {year.year}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <p className="text-sm text-muted-foreground">
                                        Copying brings over what you planned and
                                        your salary. It never copies
                                        transactions — those belong to the year
                                        they happened in.
                                    </p>

                                    <InputError message={errors.copy_from_id} />
                                </div>
                            )}

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="create-year-button"
                            >
                                Start planning
                            </Button>
                        </>
                    )}
                </Form>
            </PageShell>
        </AppLayout>
    );
}
