import { Table as TableIcon } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * Every chart can be read as a table (NFR-04).
 *
 * A chart is a picture of numbers, and a picture is no use to a screen reader or to
 * someone who wants the exact figure rather than the shape. This wraps a chart with the
 * same data in a form anyone can read, rather than leaving accessibility to a summary
 * nobody maintains.
 */
export default function ChartDataTable({
    caption,
    columns,
    rows,
    children,
}: {
    caption: string;
    columns: string[];
    rows: (string | number)[][];
    children: ReactNode;
}) {
    const [asTable, setAsTable] = useState(false);

    return (
        <div className="space-y-3">
            <div className="flex justify-end">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setAsTable(!asTable)}
                    data-testid="toggle-chart-table"
                >
                    <TableIcon className="size-4" aria-hidden="true" />
                    {asTable ? 'View as chart' : 'View as table'}
                </Button>
            </div>

            {asTable ? (
                <div className="overflow-x-auto">
                    <Table>
                        <caption className="sr-only">{caption}</caption>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => (
                                    <TableHead key={column}>{column}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={String(row[0])}>
                                    {row.map((cell, index) => (
                                        <TableCell
                                            key={index}
                                            className={
                                                index === 0
                                                    ? ''
                                                    : 'tabular-nums'
                                            }
                                        >
                                            {cell}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            ) : (
                children
            )}
        </div>
    );
}
