import type { ReactNode } from 'react';
import ChartDataTable from '@/components/finance/chart-data-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * The frame every dashboard chart shares: a title, the chart, and the same figures as a
 * table for anyone who cannot read the picture (NFR-04).
 */
export default function ChartCard({
    title,
    caption,
    columns,
    rows,
    children,
}: {
    title: string;
    caption: string;
    columns: string[];
    rows: (string | number)[][];
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <ChartDataTable caption={caption} columns={columns} rows={rows}>
                    {children}
                </ChartDataTable>
            </CardContent>
        </Card>
    );
}

/**
 * What stands in while a deferred chart is still on its way (FE-08).
 *
 * Shaped like the chart it replaces, so the page does not jump when the real one lands.
 */
export function ChartSkeleton({ title }: { title: string }) {
    return (
        <Card aria-busy="true">
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <Skeleton className="h-64 w-full" />
                <Skeleton className="h-4 w-32" />
                <span className="sr-only">Loading {title}</span>
            </CardContent>
        </Card>
    );
}

/**
 * A chart with nothing to draw yet. Saying so is better than an empty frame the user has
 * to interpret (UX-06).
 */
export function ChartEmpty({
    title,
    message,
}: {
    title: string;
    message: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="py-10 text-center text-sm text-muted-foreground">
                    {message}
                </p>
            </CardContent>
        </Card>
    );
}
