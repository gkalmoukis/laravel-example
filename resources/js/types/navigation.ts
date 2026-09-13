import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Path prefixes that also count as "you are here". An entry that opens one tab of a
     * hub has to stay lit on the others, and `href` alone cannot say that: the Plan entry
     * points at the income tab but owns the whole of `/years/{year}/plan` and the
     * un-year-scoped `/subscriptions` beside it.
     */
    match?: string[];
};
