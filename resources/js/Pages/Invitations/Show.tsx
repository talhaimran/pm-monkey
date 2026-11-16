import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { MailX } from 'lucide-react';

interface InvitationDetails {
    workspace: string;
    project: string | null;
    projectName: string | null;
    role: string;
    email: string;
    inviter: string | null;
}

/** The emailed invitation link: accept when signed in with the invited address, else register or log in first. */
export default function Show({ token, error, signedIn, invitation }: { token: string; error: string | null; signedIn: boolean; invitation: InvitationDetails | null }) {
    const { post, processing, errors } = useForm<{ invitation?: string }>({});

    return (
        <GuestLayout>
            <Head title="Invitation" />

            {invitation ? (
                <>
                    <h1 className="text-heading-2xl text-text">Join {invitation.workspace}</h1>
                    <p className="mt-2 text-muted">
                        {invitation.inviter ?? 'An admin'} invited <strong className="text-text">{invitation.email}</strong>
                        {invitation.project ? (
                            <>
                                {' '}to the project <strong className="text-text">{invitation.projectName}</strong> ({invitation.project}) as {invitation.role}.
                            </>
                        ) : (
                            <> to join as {invitation.role}.</>
                        )}
                    </p>
                </>
            ) : (
                <div className="flex flex-col items-center gap-3 text-center">
                    <span className="flex h-10 w-10 items-center justify-center rounded-tile bg-card-2 text-muted">
                        <MailX className="h-5 w-5" aria-hidden="true" />
                    </span>
                    <h1 className="text-heading-2xl text-text">Invitation</h1>
                </div>
            )}

            {error && (
                <p role="alert" className="mt-4 rounded-item tint-amber px-3 py-2 text-sm text-text">
                    {error}
                </p>
            )}
            <InputError message={errors.invitation} className="mt-2" />

            <div className="mt-6 flex flex-wrap items-center justify-end gap-4">
                {!signedIn && invitation && (
                    <>
                        <Link href={route('login')} className="rounded-ctl text-sm text-muted underline hover:text-text">
                            Log in
                        </Link>
                        <PrimaryButton onClick={() => router.visit(route('register', { invitation: token }))}>Create account</PrimaryButton>
                    </>
                )}
                {signedIn && !error && (
                    <PrimaryButton disabled={processing} onClick={() => post(route('invitations.join', token))}>
                        Accept invitation
                    </PrimaryButton>
                )}
                {signedIn && error && (
                    <Link href="/dashboard" className="rounded-ctl text-sm text-muted underline hover:text-text">
                        Go to your work
                    </Link>
                )}
            </div>
        </GuestLayout>
    );
}
