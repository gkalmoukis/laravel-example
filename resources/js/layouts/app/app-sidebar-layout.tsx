import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import QuickAddProvider from '@/components/transactions/quick-add-provider';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <QuickAddProvider>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </QuickAddProvider>
    );
}
