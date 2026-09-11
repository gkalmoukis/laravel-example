import { Form, Head } from '@inertiajs/react';
import InvitationAcceptanceController from '@/actions/App/Http/Controllers/InvitationAcceptanceController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    token: string;
    email: string;
};

export default function AcceptInvitation({ token, email }: Props) {
    return (
        <AuthLayout
            title="Accept your invitation"
            description="Choose a name and password to finish creating your account"
        >
            <Head title="Accept invitation" />

            <Form
                {...InvitationAcceptanceController.store.form({ token })}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                {/*
                                    Read-only: the account is created for the address the
                                    invitation was sent to, never one supplied here.
                                */}
                                <Input
                                    id="email"
                                    type="email"
                                    value={email}
                                    readOnly
                                    disabled
                                    autoComplete="username"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    placeholder="At least 12 characters"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Repeat your password"
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                disabled={processing}
                                data-test="accept-invitation-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
