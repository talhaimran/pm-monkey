import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import TwoFactorForm, { TwoFactorState } from './Partials/TwoFactorForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    twoFactor,
}: PageProps<{ mustVerifyEmail: boolean; status?: string; twoFactor: TwoFactorState }>) {
    // "verification-link-sent" belongs to the profile form; anything else is a notice, e.g. a workspace requiring 2FA.
    const notice = status && status !== 'verification-link-sent' ? status : null;

    return (
        <AuthenticatedLayout
            header={
                <h1 className="text-display text-text">
                    Profile
                </h1>
            }
        >
            <Head title="Profile" />

            <div className="pb-10">
                <div className="max-w-4xl space-y-6 px-4 md:px-8">
                    {notice && (
                        <p role="status" className="rounded-card tint-info p-4 text-md">
                            {notice}
                        </p>
                    )}

                    <div className="rounded-card bg-card p-5 shadow sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="rounded-card bg-card p-5 shadow sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="rounded-card bg-card p-5 shadow sm:p-8">
                        <TwoFactorForm state={twoFactor} className="max-w-xl" />
                    </div>

                    <div className="rounded-card bg-card p-5 shadow sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
