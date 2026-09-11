import type { Abilities, Auth } from '@/types/auth';
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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
