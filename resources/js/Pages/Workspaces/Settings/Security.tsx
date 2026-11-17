import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import WorkspaceSettingsHeader from '@/Components/WorkspaceSettingsHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** Spec 7.0e: require two-factor for everyone in the workspace. Sign-in methods arrive with Google and GitHub sign-in. */
export default function Security({ require2fa, members, membersWithout2fa }: { require2fa: boolean; members: number; membersWithout2fa: number }) {
    const form = useForm({ require_2fa: require2fa });
    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('settings.security.update'), { preserveScroll: true });
    };
    const turningOn = form.data.require_2fa && !require2fa;

    return (
        <AuthenticatedLayout header={<WorkspaceSettingsHeader />}>
            <Head title="Security" />

            <div className="max-w-3xl space-y-6 px-4 pb-10 md:px-8">
                <section className="rounded-card bg-card p-5 shadow sm:p-6">
                    <h2 className="text-lg text-text">Two-factor authentication</h2>
                    <p className="mt-1 text-sm text-muted">
                        {membersWithout2fa === 0
                            ? `All ${members} members have two-factor on.`
                            : `${membersWithout2fa} of ${members} members don’t have two-factor on yet.`}{' '}
                        <Link href={route('settings.members')} className="underline hover:text-text">
                            See who
                        </Link>
                    </p>
                    <form onSubmit={save} className="mt-4 space-y-4">
                        <label className="flex min-h-control-h items-center gap-3">
                            <Checkbox checked={form.data.require_2fa} onChange={(e) => form.setData('require_2fa', e.target.checked)} />
                            <span className="text-md text-text">
                                Require two-factor authentication
                                <span className="block text-sm text-muted">Members without it are sent to set it up before they can use this workspace.</span>
                            </span>
                        </label>
                        {turningOn && membersWithout2fa > 0 && (
                            <p role="status" className="rounded-card tint-warn p-3 text-sm">
                                {membersWithout2fa} {membersWithout2fa === 1 ? 'member' : 'members'} will have to set it up on their next visit.
                            </p>
                        )}
                        <p className="text-sm text-muted">API tokens that already exist keep working. Creating a new token will need two-factor on.</p>
                        <InputError message={form.errors.require_2fa} />
                        <div className="flex justify-end">
                            <PrimaryButton disabled={form.processing || !form.isDirty}>Save changes</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
