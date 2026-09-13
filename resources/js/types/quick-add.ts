export type QuickAddCategory = {
    id: number;
    name: string;
    type: string;
    subcategories: { id: number; name: string }[];
};

export type QuickAddOptions = {
    categories: QuickAddCategory[];
    accounts: { id: number; name: string }[];
    defaultAccountId: number | null;
    mostUsedCategoryIds: Record<string, number[]>;
};

/** What the combobox resolves to: a category, optionally narrowed to one of its children. */
export type CategoryChoice = {
    categoryId: number;
    subcategoryId: number | null;
    label: string;
};
