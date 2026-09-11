import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';

/**
 * Shown for invalid, expired, revoked and already-used links alike. The copy is
 * deliberately identical in every case so nothing reveals whether an address was ever
 * invited (INV-07).
 */
export default function InvitationInvalid() {
    return (
        <AuthLayout
            title="This invitation link is no longer valid"
            description="It may have expired, already been used, or been withdrawn."
        >
            <Head title="Invitation unavailable" />

            <p className="text-center text-sm text-muted-foreground">
                Ask whoever invited you to send a new invitation. If you already
                have an account, you can{' '}
                <TextLink href={login()}>log in</TextLink> instead.
            </p>
        </AuthLayout>
    );
}
