import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import InvitationResendController from '@/actions/App/Http/Controllers/InvitationResendController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/invitations';
import type { BreadcrumbItem } from '@/types';

type InvitationStatus = 'pending' | 'accepted' | 'revoked' | 'expired';

type Invitation = {
    id: string;
    email: string;
    isAdmin: boolean;
    status: InvitationStatus;
    invitedBy: string | null;
    expiresAt: string;
    createdAt: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Invitations',
        href: index(),
    },
];

const statusVariant: Record<
    InvitationStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'default',
    accepted: 'secondary',
    revoked: 'destructive',
    expired: 'outline',
};

const statusLabel: Record<InvitationStatus, string> = {
    pending: 'Pending',
    accepted: 'Accepted',
    revoked: 'Revoked',
    expired: 'Expired',
};

export default function InvitationsIndex({
    invitations,
}: {
    invitations: Invitation[];
}) {
    const [revoking, setRevoking] = useState<Invitation | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invitations" />

            <h1 className="sr-only">Invitations</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Invite someone"
                        description="They will receive a single-use link that expires after seven days"
                    />

                    <Form
                        {...InvitationController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoComplete="off"
                                        placeholder="person@example.com"
                                    />

                                    <InputError message={errors.email} />
                                </div>

                                <div className="flex items-center gap-2">
                                    <Checkbox id="is_admin" name="is_admin" />

                                    <Label
                                        htmlFor="is_admin"
                                        className="font-normal"
                                    >
                                        Allow them to invite others
                                    </Label>
                                </div>

                                <Button type="submit" disabled={processing}>
                                    Send invitation
                                </Button>
                            </>
                        )}
                    </Form>

                    <Heading
                        variant="small"
                        title="Sent invitations"
                        description="Resend or withdraw invitations that have not been used yet"
                    />

                    {invitations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No invitations yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Invited by</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {invitations.map((invitation) => (
                                        <TableRow key={invitation.id}>
                                            <TableCell className="font-medium">
                                                {invitation.email}
                                                {invitation.isAdmin && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ml-2"
                                                    >
                                                        Can invite
                                                    </Badge>
                                                )}
                                            </TableCell>

                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        statusVariant[
                                                            invitation.status
                                                        ]
                                                    }
                                                >
                                                    {
                                                        statusLabel[
                                                            invitation.status
                                                        ]
                                                    }
                                                </Badge>
                                            </TableCell>

                                            <TableCell className="text-muted-foreground">
                                                {invitation.invitedBy ?? '—'}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                {invitation.status ===
                                                    'pending' && (
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    InvitationResendController.store.url(
                                                                        {
                                                                            invitation:
                                                                                invitation.id,
                                                                        },
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Resend
                                                        </Button>

                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setRevoking(
                                                                    invitation,
                                                                )
                                                            }
                                                        >
                                                            Revoke
                                                        </Button>
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </SettingsLayout>

            <Dialog
                open={revoking !== null}
                onOpenChange={(open) => !open && setRevoking(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revoke this invitation?</DialogTitle>

                        <DialogDescription>
                            The link sent to {revoking?.email} will stop working
                            immediately. You can always invite them again.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="secondary"
                            onClick={() => setRevoking(null)}
                        >
                            Keep it
                        </Button>

                        <Button
                            variant="destructive"
                            data-test="confirm-revoke-button"
                            onClick={() => {
                                if (revoking) {
                                    router.delete(
                                        InvitationController.destroy.url({
                                            invitation: revoking.id,
                                        }),
                                        { preserveScroll: true },
                                    );
                                }

                                setRevoking(null);
                            }}
                        >
                            Revoke invitation
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
