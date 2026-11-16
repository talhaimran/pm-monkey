import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router } from '@inertiajs/react';
import { Lock } from 'lucide-react';

/** D11: signed in, but no longer in any workspace. */
export default function NoAccess({ message, canCreate }: { message: string; canCreate: boolean }) {
    return (
        <GuestLayout>
            <Head title="No access" />
            <div className="flex flex-col items-center gap-3 text-center">
                <span className="flex h-10 w-10 items-center justify-center rounded-tile bg-card-2 text-muted">
                    <Lock className="h-5 w-5" aria-hidden="true" />
                </span>
                <h1 className="text-heading-2xl text-text">No access</h1>
                <p className="text-muted">{message}</p>
                <div className="mt-3 flex flex-wrap justify-center gap-3">
                    {canCreate && (
                        <PrimaryButton onClick={() => router.visit(route('workspaces.create'))}>Create workspace</PrimaryButton>
                    )}
                    <SecondaryButton onClick={() => router.visit(route('profile.edit'))}>Profile</SecondaryButton>
                    <SecondaryButton onClick={() => router.post(route('logout'))}>Log out</SecondaryButton>
                </div>
            </div>
        </GuestLayout>
    );
}
