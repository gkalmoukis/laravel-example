import { Form, Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import CategoryActivationController from '@/actions/App/Http/Controllers/CategoryActivationController';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import SubcategoryParentController from '@/actions/App/Http/Controllers/SubcategoryParentController';
import { EmptyRow } from '@/components/finance/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/categories';
import type { BreadcrumbItem } from '@/types';

type CategoryType = 'income' | 'expense';

type Category = {
    id: number;
    name: string;
    type: CategoryType;
    isActive: boolean;
    isEssential: boolean;
    isIrregular: boolean;
    isSystem: boolean;
    sortOrder: number;
};

type ParentCategory = Category & { children: Category[] };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Categories', href: index() }];

function patch(category: Category, data: Record<string, unknown>) {
    router.patch(
        CategoryController.update.url({ category: category.id }),
        { name: category.name, ...data },
        { preserveScroll: true },
    );
}

export default function Categories({
    categories,
}: {
    categories: ParentCategory[];
}) {
    const [type, setType] = useState<CategoryType>('expense');
    const [moving, setMoving] = useState<Category | null>(null);
    const [deactivating, setDeactivating] = useState<Category | null>(null);

    const visible = categories.filter((category) => category.type === type);
    const moveTargets = categories.filter(
        (category) => category.type === type && category.id !== moving?.id,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />

            <h1 className="sr-only">Categories</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Categories"
                        description="How your money is grouped. Categories can hold one level of subcategories."
                    />

                    {/*
                     * A filter over one list rather than two panels, so these are
                     * toggle buttons: Radix tabs would promise a tab panel through
                     * `aria-controls` that this page never renders (NFR-06).
                     */}
                    <ToggleGroup
                        type="single"
                        value={type}
                        onValueChange={(value) => {
                            if (value) {
                                setType(value as CategoryType);
                            }
                        }}
                        variant="outline"
                    >
                        <ToggleGroupItem value="expense">
                            Expenses
                        </ToggleGroupItem>
                        <ToggleGroupItem value="income">Income</ToggleGroupItem>
                    </ToggleGroup>

                    <Form
                        {...CategoryController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex flex-col gap-4 sm:flex-row sm:items-start"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="type" value={type} />

                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="name">
                                        New {type} category
                                    </Label>

                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        placeholder="Groceries"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="sm:mt-8"
                                    data-test="add-category-button"
                                >
                                    Add category
                                </Button>
                            </>
                        )}
                    </Form>

                    <ul className="divide-y rounded-md border">
                        {visible.length === 0 && (
                            <EmptyRow
                                message={`No ${type} categories yet.`}
                                hint="Add one above to start filing transactions under it."
                            />
                        )}

                        {visible.map((category, position) => (
                            <li key={category.id} className="p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {category.name}
                                        </span>

                                        {category.isSystem && (
                                            <Badge variant="secondary">
                                                Built-in
                                            </Badge>
                                        )}

                                        {!category.isActive && (
                                            <Badge variant="outline">
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Move ${category.name} up`}
                                            disabled={position === 0}
                                            onClick={() =>
                                                patch(category, {
                                                    sort_order:
                                                        visible[position - 1]
                                                            ?.sortOrder ?? 0,
                                                })
                                            }
                                        >
                                            <ChevronUp className="h-4 w-4" />
                                        </Button>

                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Move ${category.name} down`}
                                            disabled={
                                                position === visible.length - 1
                                            }
                                            onClick={() =>
                                                patch(category, {
                                                    sort_order:
                                                        visible[position + 1]
                                                            ?.sortOrder ?? 0,
                                                })
                                            }
                                        >
                                            <ChevronDown className="h-4 w-4" />
                                        </Button>

                                        {category.isActive ? (
                                            !category.isSystem && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setDeactivating(
                                                            category,
                                                        )
                                                    }
                                                >
                                                    Deactivate
                                                </Button>
                                            )
                                        ) : (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        CategoryActivationController.store.url(
                                                            {
                                                                category:
                                                                    category.id,
                                                            },
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Reactivate
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                {type === 'expense' && (
                                    <div className="mt-3 flex flex-wrap gap-6">
                                        <div className="flex items-center gap-2">
                                            <Switch
                                                id={`essential-${category.id}`}
                                                checked={category.isEssential}
                                                onCheckedChange={(checked) =>
                                                    patch(category, {
                                                        is_essential: checked,
                                                        is_irregular:
                                                            category.isIrregular,
                                                    })
                                                }
                                            />

                                            <Label
                                                htmlFor={`essential-${category.id}`}
                                                className="font-normal"
                                            >
                                                Essential
                                            </Label>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Switch
                                                id={`irregular-${category.id}`}
                                                checked={category.isIrregular}
                                                onCheckedChange={(checked) =>
                                                    patch(category, {
                                                        is_essential:
                                                            category.isEssential,
                                                        is_irregular: checked,
                                                    })
                                                }
                                            />

                                            <Label
                                                htmlFor={`irregular-${category.id}`}
                                                className="font-normal"
                                            >
                                                Irregular by default
                                            </Label>
                                        </div>
                                    </div>
                                )}

                                {category.children.length > 0 && (
                                    <ul className="mt-3 space-y-1 border-l pl-4">
                                        {category.children.map((child) => (
                                            <li
                                                key={child.id}
                                                className="flex flex-wrap items-center justify-between gap-2 text-sm"
                                            >
                                                <span>
                                                    {child.name}
                                                    {!child.isActive && (
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-2"
                                                        >
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </span>

                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setMoving(child)
                                                    }
                                                >
                                                    Move
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                <Form
                                    {...CategoryController.store.form()}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="mt-3 flex items-end gap-2 border-l pl-4"
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="parent_id"
                                                value={category.id}
                                            />

                                            <div className="grid flex-1 gap-2">
                                                <Label
                                                    htmlFor={`sub-${category.id}`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Add a subcategory
                                                </Label>

                                                <Input
                                                    id={`sub-${category.id}`}
                                                    name="name"
                                                    required
                                                    placeholder="Supermarket"
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Add
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                </div>
            </SettingsLayout>

            <Dialog
                open={moving !== null}
                onOpenChange={(open) => !open && setMoving(null)}
            >
                <DialogContent>
                    <Form
                        {...SubcategoryParentController.update.form({
                            category: moving?.id ?? 0,
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setMoving(null)}
                    >
                        {({ processing }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Move {moving?.name}
                                    </DialogTitle>

                                    <DialogDescription>
                                        Choose the category it should sit under.
                                        Anything already recorded against it can
                                        move with it, or stay where it is.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-4 py-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="parent_id">
                                            New parent
                                        </Label>

                                        <Select name="parent_id">
                                            <SelectTrigger id="parent_id">
                                                <SelectValue placeholder="Pick a category" />
                                            </SelectTrigger>

                                            <SelectContent>
                                                {moveTargets.map((target) => (
                                                    <SelectItem
                                                        key={target.id}
                                                        value={String(
                                                            target.id,
                                                        )}
                                                    >
                                                        {target.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Switch
                                            id="update_existing"
                                            name="update_existing"
                                            defaultChecked
                                        />

                                        <Label
                                            htmlFor="update_existing"
                                            className="font-normal"
                                        >
                                            Move everything already recorded
                                            under it too
                                        </Label>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setMoving(null)}
                                    >
                                        Cancel
                                    </Button>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="confirm-move-button"
                                    >
                                        Move
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deactivating !== null}
                onOpenChange={(open) => !open && setDeactivating(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Deactivate {deactivating?.name}?
                        </DialogTitle>

                        <DialogDescription>
                            It will stop appearing when you record a
                            transaction, along with its subcategories.
                            Everything already recorded keeps it, and you can
                            bring it back at any time.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="secondary"
                            onClick={() => setDeactivating(null)}
                        >
                            Keep it active
                        </Button>

                        <Button
                            variant="destructive"
                            data-test="confirm-deactivate-category-button"
                            onClick={() => {
                                if (deactivating) {
                                    router.delete(
                                        CategoryController.destroy.url({
                                            category: deactivating.id,
                                        }),
                                        { preserveScroll: true },
                                    );
                                }

                                setDeactivating(null);
                            }}
                        >
                            Deactivate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
