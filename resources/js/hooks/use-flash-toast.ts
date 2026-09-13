import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Turns a flashed message into a toast (FE-13).
 *
 * Controllers throughout the application say `->with('status', '…')`, which is Laravel's
 * own convention and reads well at the call site. A `toast` key is also honoured for the
 * cases that need to choose the tone, since a plain status is always a success.
 */
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = event.detail.flash as {
                toast?: FlashToast;
                status?: string;
            };

            if (flash.toast) {
                toast[flash.toast.type](flash.toast.message);

                return;
            }

            if (flash.status) {
                toast.success(flash.status);
            }
        });
    }, []);
}
