import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as recategorize } from '@/routes/transaction-category';
import type { TransactionOptions } from '@/types/transactions';

const NONE = 'none';

/**
 * Refiling a batch (TXL-04).
 *
 * Appears only once something is selected, and says how many, because "change category" is
 * a different decision for three rows than for three hundred.
 */
export default function BulkActionsBar({
    selectedIds,
    options,
    onDone,
}: {
    selectedIds: number[];
    options: TransactionOptions;
    onDone: () => void;
}) {
    const [categoryId, setCategoryId] = useState<string>('');
    const [subcategoryId, setSubcategoryId] = useState<string>(NONE);
    const [saving, setSaving] = useState(false);

    if (selectedIds.length === 0) {
        return null;
    }

    const subcategories =
        options.categories.find((c) => String(c.id) === categoryId)
            ?.subcategories ?? [];

    const apply = () => {
        setSaving(true);

        router.patch(
            recategorize.url(),
            {
                transaction_ids: selectedIds,
                category_id: Number(categoryId),
                subcategory_id:
                    subcategoryId === NONE ? null : Number(subcategoryId),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    onDone();
                },
            },
        );
    };

    return (
        <div
            className="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/40 p-4"
            data-testid="bulk-actions"
        >
            <p className="text-sm font-medium">
                {selectedIds.length} selected
                {/*
                 * Selection is page-scoped on purpose (TXL-04) — saying so stops
                 * "3 selected" being read as three out of the whole filter.
                 */}
                <span className="font-normal text-muted-foreground">
                    {' '}
                    on this page
                </span>
            </p>

            <div className="min-w-48">
                <Label htmlFor="bulk_category">Change category to</Label>
                <Select
                    value={categoryId}
                    onValueChange={(value) => {
                        setCategoryId(value);
                        setSubcategoryId(NONE);
                    }}
                >
                    <SelectTrigger
                        id="bulk_category"
                        className="mt-1 w-full"
                        data-testid="bulk-category"
                    >
                        <SelectValue placeholder="Pick a category" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {subcategories.length > 0 && (
                <div className="min-w-40">
                    <Label htmlFor="bulk_subcategory">Subcategory</Label>
                    <Select
                        value={subcategoryId}
                        onValueChange={setSubcategoryId}
                    >
                        <SelectTrigger
                            id="bulk_subcategory"
                            className="mt-1 w-full"
                        >
                            <SelectValue placeholder="None" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>None</SelectItem>
                            {subcategories.map((subcategory) => (
                                <SelectItem
                                    key={subcategory.id}
                                    value={String(subcategory.id)}
                                >
                                    {subcategory.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            <Button
                disabled={categoryId === '' || saving}
                onClick={apply}
                data-testid="apply-bulk-category"
            >
                Apply
            </Button>

            <Button
                variant="ghost"
                onClick={onDone}
                data-testid="clear-selection"
            >
                <X className="size-4" />
                Clear
            </Button>
        </div>
    );
}
