import type { Abilities, Auth } from '@/types/auth';
import type { Preferences } from '@/types/preferences';
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            abilities: Abilities;
            preferences: Preferences | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
