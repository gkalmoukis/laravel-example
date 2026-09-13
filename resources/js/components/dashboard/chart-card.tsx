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
            <CardContent>
                {/*
                 * Shaped like a chart rather than one grey slab: an axis down the left,
                 * a baseline, and bars of uneven height. A block the size of the chart
                 * reserves the space but still reads as "something is broken".
                 */}
                <div className="flex h-64 gap-3">
                    <div className="flex w-16 shrink-0 flex-col justify-between py-1">
                        {[0, 1, 2, 3, 4].map((tick) => (
                            <Skeleton key={tick} className="h-3 w-full" />
                        ))}
                    </div>

                    <div className="flex flex-1 items-end gap-2 border-b border-l pb-2 pl-2">
                        {[45, 70, 55, 85, 40, 65, 75, 50].map(
                            (height, index) => (
                                <Skeleton
                                    key={`${height}-${index}`}
                                    className="flex-1"
                                    style={{ height: `${height}%` }}
                                />
                            ),
                        )}
                    </div>
                </div>

                <Skeleton className="mt-3 h-4 w-32" />
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
