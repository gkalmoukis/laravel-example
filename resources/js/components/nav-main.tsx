import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const isActive = (item: NavItem): boolean => {
        if (item.match === undefined) {
            return isCurrentOrParentUrl(item.href);
        }

        return item.match.some((path) => isCurrentOrParentUrl(path));
    };

    // Settings is the one entry that is not about the money itself, so it sits apart
    // rather than at the end of the same list.
    const money = items.filter((item) => item.title !== 'Settings');
    const rest = items.filter((item) => item.title === 'Settings');

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarMenu>
                    {money.map((item) => (
                        <NavEntry
                            key={item.title}
                            item={item}
                            active={isActive(item)}
                        />
                    ))}
                </SidebarMenu>
            </SidebarGroup>

            <SidebarSeparator className="mx-2 my-2" />

            <SidebarGroup className="px-2 py-0">
                <SidebarMenu>
                    {rest.map((item) => (
                        <NavEntry
                            key={item.title}
                            item={item}
                            active={isActive(item)}
                        />
                    ))}
                </SidebarMenu>
            </SidebarGroup>
        </>
    );
}

function NavEntry({ item, active }: { item: NavItem; active: boolean }) {
    const id = `nav-${item.title.toLowerCase().replaceAll(' ', '-')}`;

    return (
        <SidebarMenuItem data-testid="sidebar-nav-item">
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} id={id} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
