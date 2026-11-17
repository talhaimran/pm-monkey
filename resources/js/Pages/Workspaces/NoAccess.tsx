import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router } from '@inertiajs/react';
import { Lock } from 'lucide-react';

/** D11: signed in, but in no workspace; also D2: the workspace is scheduled for deletion, and its Owner can undo. */
export default function NoAccess({ message, canCreate, restoreUrl }: { message: string; canCreate: boolean; restoreUrl: string | null }) {
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
                    {restoreUrl && <PrimaryButton onClick={() => router.post(restoreUrl)}>Undo deletion</PrimaryButton>}
                    {canCreate && !restoreUrl && (
                        <PrimaryButton onClick={() => router.visit(route('workspaces.create'))}>Create workspace</PrimaryButton>
                    )}
                    <SecondaryButton onClick={() => router.visit(route('profile.edit'))}>Profile</SecondaryButton>
                    <SecondaryButton onClick={() => router.post(route('logout'))}>Log out</SecondaryButton>
                </div>
            </div>
        </GuestLayout>
    );
}
