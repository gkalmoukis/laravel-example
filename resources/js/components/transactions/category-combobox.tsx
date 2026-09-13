import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import type {
    CategoryChoice,
    QuickAddCategory,
    QuickAddOptions,
} from '@/types/quick-add';

type Entry = CategoryChoice & { keywords: string };

/**
 * Every way the user might name a place to file money: the category itself, and each of
 * its subcategories.
 *
 * Subcategories are offered as first-class choices because that is how people think —
 * typing "Supermarket" should file the transaction under Food & Groceries › Supermarket
 * without the user first having to remember which parent it lives under (TXQ-04).
 */
function entriesFor(category: QuickAddCategory): Entry[] {
    return [
        {
            categoryId: category.id,
            subcategoryId: null,
            label: category.name,
            keywords: category.name,
        },
        ...category.subcategories.map((subcategory) => ({
            categoryId: category.id,
            subcategoryId: subcategory.id,
            label: `${category.name} › ${subcategory.name}`,
            keywords: `${subcategory.name} ${category.name}`,
        })),
    ];
}

/**
 * On a desktop this is a combobox in a popover; on a phone the quick-add form is already a
 * sheet, so the list is part of the form rather than a second layer floating over a small
 * screen. Nesting one overlay inside another also leaves the options visible but
 * unclickable, which is a poor way to discover a layout decision.
 *
 * `modal` on the Popover is load-bearing for the desktop case: without it the popover
 * portals outside the dialog, where the dialog's own `pointer-events: none` guard applies.
 */
export default function CategoryCombobox({
    options,
    type,
    value,
    onChange,
    invalid,
}: {
    options: QuickAddOptions;
    type: string;
    value: CategoryChoice | null;
    onChange: (choice: CategoryChoice) => void;
    invalid?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const isMobile = useIsMobile();

    const { habits, rest } = useMemo(() => {
        const ofType = options.categories.filter(
            (category) => category.type === type,
        );

        const mostUsed = options.mostUsedCategoryIds[type] ?? [];

        const habitual = mostUsed
            .map((id) => ofType.find((category) => category.id === id))
            .filter((category): category is QuickAddCategory =>
                Boolean(category),
            );

        return {
            habits: habitual.map((category) => entriesFor(category)[0]),
            rest: ofType.flatMap(entriesFor),
        };
    }, [options, type]);

    const choose = (entry: Entry) => {
        onChange({
            categoryId: entry.categoryId,
            subcategoryId: entry.subcategoryId,
            label: entry.label,
        });
        setOpen(false);
    };

    const ticked = (entry: Entry) =>
        value?.categoryId === entry.categoryId &&
        value.subcategoryId === entry.subcategoryId;

    const list = (
        <Command>
            <CommandInput id="category-search" placeholder="Type a category…" />
            <CommandList>
                <CommandEmpty>No category matches.</CommandEmpty>

                {habits.length > 0 && (
                    <CommandGroup heading="You use these most">
                        {habits.map((entry) => (
                            <CommandItem
                                key={`habit-${entry.categoryId}`}
                                value={entry.keywords}
                                onSelect={() => choose(entry)}
                            >
                                <Check
                                    className={cn(
                                        'size-4',
                                        ticked(entry)
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )}
                                />
                                {entry.label}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                <CommandGroup heading="All categories">
                    {rest.map((entry) => (
                        <CommandItem
                            key={`${entry.categoryId}-${entry.subcategoryId ?? 'none'}`}
                            value={entry.keywords}
                            onSelect={() => choose(entry)}
                        >
                            <Check
                                className={cn(
                                    'size-4',
                                    ticked(entry) ? 'opacity-100' : 'opacity-0',
                                )}
                            />
                            {entry.label}
                        </CommandItem>
                    ))}
                </CommandGroup>
            </CommandList>
        </Command>
    );

    if (isMobile) {
        return (
            <div
                className="rounded-md border"
                data-testid="category-combobox"
                aria-invalid={invalid}
            >
                {list}
            </div>
        );
    }

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <Button
                    id="category"
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    className={cn(
                        'w-full justify-between font-normal',
                        !value && 'text-muted-foreground',
                    )}
                    data-testid="category-combobox"
                >
                    {value?.label ?? 'Pick a category'}
                    <ChevronsUpDown className="size-4 opacity-50" />
                </Button>
            </PopoverTrigger>

            <PopoverContent
                className="w-(--radix-popover-trigger-width) p-0"
                align="start"
            >
                {list}
            </PopoverContent>
        </Popover>
    );
}
