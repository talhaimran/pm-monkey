import Checkbox from '@/Components/Checkbox';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import WarningConfirm from '@/Components/WarningConfirm';
import WorkspaceSettingsHeader from '@/Components/WorkspaceSettingsHeader';
import { Mark } from '@/Components/WorkspaceSwitcher';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Settings = { name: string; slug: string; members_can_create_projects: boolean };

const card = 'rounded-card bg-card p-5 shadow sm:p-6';

export default function General({ settings, can }: { settings: Settings; can: { delete: boolean } }) {
    const form = useForm(settings);
    const remove = useForm({ confirm_slug: '' });
    const [confirming, setConfirming] = useState(false);

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('settings.general.update'), { preserveScroll: true });
    };

    const close = () => {
        setConfirming(false);
        remove.reset();
        remove.clearErrors();
    };

    return (
        <AuthenticatedLayout header={<WorkspaceSettingsHeader />}>
            <Head title="Workspace settings" />

            <div className="max-w-3xl space-y-6 px-4 pb-10 md:px-8">
                <section className={card}>
                    <h2 className="text-lg text-text">General</h2>
                    <form onSubmit={save} className="mt-4 space-y-4">
                        <div className="flex items-center gap-3">
                            <Mark name={form.data.name || settings.name} tone="tint-cobalt" size="h-12 w-12 text-heading-2xl" />
                            <p className="text-sm text-muted">The workspace's mark is its first letter. Logo upload comes later.</p>
                        </div>
                        <div>
                            <InputLabel htmlFor="name" value="Name" />
                            <TextInput id="name" value={form.data.name} maxLength={100} className="mt-1 block w-full" onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="slug" value="Address" />
                            <div className="mt-1 flex items-center gap-1 text-muted">
                                <span aria-hidden="true">/</span>
                                <TextInput id="slug" value={form.data.slug} maxLength={40} className="block w-full" aria-describedby="slug-hint" onChange={(e) => form.setData('slug', e.target.value.toLowerCase())} required />
                            </div>
                            <p id="slug-hint" className="mt-1 text-sm text-muted">
                                3–40 lowercase letters, digits or hyphens. Links to the old address keep working.
                            </p>
                            <InputError message={form.errors.slug} className="mt-2" />
                        </div>
                        <label className="flex min-h-control-h items-center gap-3">
                            <Checkbox checked={form.data.members_can_create_projects} onChange={(e) => form.setData('members_can_create_projects', e.target.checked)} />
                            <span className="text-md text-text">
                                Members can create projects
                                <span className="block text-sm text-muted">Owners and Admins always can.</span>
                            </span>
                        </label>
                        <div className="flex justify-end">
                            <PrimaryButton disabled={form.processing || !form.isDirty}>Save changes</PrimaryButton>
                        </div>
                    </form>
                </section>

                {can.delete && (
                    <section className={card}>
                        <h2 className="text-lg text-t-bad">Delete workspace</h2>
                        <p className="mt-1 text-sm text-muted">
                            Everything in {settings.name} goes after 7 days: projects, issues, comments, invitations and API tokens. Until then nobody can use it, and you can undo.
                        </p>
                        <DangerButton className="mt-4" onClick={() => setConfirming(true)}>
                            Delete workspace
                        </DangerButton>
                    </section>
                )}
            </div>

            <WarningConfirm
                open={confirming}
                title={`Delete ${settings.name}?`}
                warnings={[
                    { text: 'Export anything you want to keep first.', reason: 'After 7 days nothing in this workspace can be recovered.' },
                    { text: 'Members are locked out straight away.', reason: 'You can undo this from the workspace address until then.' },
                ]}
                confirmLabel="Delete workspace"
                processing={remove.processing}
                onCancel={close}
                onConfirm={() => remove.delete(route('settings.destroy'), { onSuccess: close })}
            >
                <div>
                    <InputLabel htmlFor="confirm_slug" value={`Type ${settings.slug} to confirm`} />
                    <TextInput id="confirm_slug" value={remove.data.confirm_slug} className="mt-1 block w-full" autoComplete="off" onChange={(e) => remove.setData('confirm_slug', e.target.value)} />
                    <InputError message={remove.errors.confirm_slug} className="mt-2" />
                </div>
            </WarningConfirm>
        </AuthenticatedLayout>
    );
}
