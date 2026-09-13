import type { Abilities, Auth } from '@/types/auth';
import type { Preferences } from '@/types/preferences';
import type { QuickAddOptions } from '@/types/quick-add';
import type { FlashToast } from '@/types/ui';
import type { FinancialYearOption } from '@/types/years';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            // What controllers flash with ->with('status', '…').
            status?: string;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            abilities: Abilities;
            preferences: Preferences | null;
            years: FinancialYearOption[];
            selectedYear: number | null;
            quickAdd?: QuickAddOptions;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
