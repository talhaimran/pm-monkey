import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Invitation, Project, ProjectRole } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const selectClass =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';

export default function Settings({
    project,
    invitations,
    roles,
    can,
}: {
    project: Project;
    invitations: Invitation[];
    roles: ProjectRole[];
    can: { manage: boolean };
}) {
    const { errors: pageErrors } = usePage().props as { errors: Record<string, string> };
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const details = useForm({
        name: project.name,
        description: project.description ?? '',
    });
    const invite = useForm({ email: '', role: 'member' as ProjectRole });

    const saveDetails: FormEventHandler = (e) => {
        e.preventDefault();
        details.patch(route('projects.update', project.key), { preserveScroll: true });
    };

    const sendInvite: FormEventHandler = (e) => {
        e.preventDefault();
        invite.post(route('projects.invitations.store', project.key), {
            preserveScroll: true,
            onSuccess: () => invite.reset('email'),
        });
    };

    const changeRole = (userId: number, role: string) =>
        router.patch(
            route('projects.members.update', [project.key, userId]),
            { role },
            { preserveScroll: true },
        );

    const removeMember = (userId: number) =>
        router.delete(route('projects.members.destroy', [project.key, userId]), {
            preserveScroll: true,
        });

    const card = 'rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800';
    const heading = 'font-medium text-gray-900 dark:text-gray-100';

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    <span className="font-mono text-indigo-600 dark:text-indigo-400">
                        {project.key}
                    </span>{' '}
                    {project.name} · Settings
                </h2>
            }
        >
            <Head title={`${project.key} settings`} />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className={card}>
                    <h3 className={heading}>Details</h3>
                    <form onSubmit={saveDetails} className="mt-4 space-y-4">
                        <div>
                            <InputLabel htmlFor="name" value="Name" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={details.data.name}
                                onChange={(e) => details.setData('name', e.target.value)}
                                disabled={!can.manage}
                                required
                            />
                            <InputError className="mt-2" message={details.errors.name} />
                        </div>
                        <div>
                            <InputLabel htmlFor="description" value="Description (Markdown)" />
                            <textarea
                                id="description"
                                rows={4}
                                className={`${selectClass} mt-1 block w-full`}
                                value={details.data.description}
                                onChange={(e) => details.setData('description', e.target.value)}
                                disabled={!can.manage}
                            />
                            <InputError className="mt-2" message={details.errors.description} />
                        </div>
                        {can.manage && (
                            <PrimaryButton disabled={details.processing}>Save</PrimaryButton>
                        )}
                    </form>
                </section>

                <section className={card}>
                    <h3 className={heading}>Members</h3>
                    <InputError className="mt-2" message={pageErrors.role ?? pageErrors.member} />
                    <ul className="mt-4 divide-y divide-gray-100 dark:divide-gray-700">
                        {project.members?.map((m) => (
                            <li key={m.id} className="flex flex-wrap items-center gap-3 py-2 text-sm">
                                <div className="min-w-0 flex-1">
                                    <div className="text-gray-900 dark:text-gray-100">{m.name}</div>
                                    <div className="truncate text-gray-500 dark:text-gray-400">{m.email}</div>
                                </div>
                                {can.manage ? (
                                    <>
                                        <select
                                            aria-label={`Role for ${m.name}`}
                                            className={selectClass}
                                            value={m.role}
                                            onChange={(e) => changeRole(m.id, e.target.value)}
                                        >
                                            {roles.map((r) => (
                                                <option key={r} value={r}>
                                                    {r}
                                                </option>
                                            ))}
                                        </select>
                                        <SecondaryButton onClick={() => removeMember(m.id)}>
                                            Remove
                                        </SecondaryButton>
                                    </>
                                ) : (
                                    <span className="text-xs uppercase text-gray-500">{m.role}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                {can.manage && (
                    <section className={card}>
                        <h3 className={heading}>Invite by email</h3>
                        <form onSubmit={sendInvite} className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div className="flex-1">
                                <InputLabel htmlFor="invite-email" value="Email" className="sr-only" />
                                <TextInput
                                    id="invite-email"
                                    type="email"
                                    className="block w-full"
                                    placeholder="teammate@example.com"
                                    value={invite.data.email}
                                    onChange={(e) => invite.setData('email', e.target.value)}
                                    required
                                />
                                <InputError className="mt-2" message={invite.errors.email} />
                            </div>
                            <select
                                aria-label="Role"
                                className={selectClass}
                                value={invite.data.role}
                                onChange={(e) => invite.setData('role', e.target.value as ProjectRole)}
                            >
                                {roles.map((r) => (
                                    <option key={r} value={r}>
                                        {r}
                                    </option>
                                ))}
                            </select>
                            <PrimaryButton disabled={invite.processing}>Invite</PrimaryButton>
                        </form>

                        {invitations.length > 0 && (
                            <ul className="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-700">
                                {invitations.map((inv) => (
                                    <li key={inv.id} className="flex items-center justify-between gap-3 py-2 text-gray-700 dark:text-gray-300">
                                        <span>
                                            {inv.email} · {inv.role} · pending
                                        </span>
                                        <SecondaryButton
                                            onClick={() =>
                                                router.delete(
                                                    route('projects.invitations.destroy', [project.key, inv.id]),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Revoke
                                        </SecondaryButton>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {can.manage && (
                    <section className={card}>
                        <h3 className="font-medium text-red-600 dark:text-red-400">Delete project</h3>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Permanently deletes {project.key} and all of its issues.
                        </p>
                        <DangerButton className="mt-4" onClick={() => setConfirmingDelete(true)}>
                            Delete project
                        </DangerButton>
                    </section>
                )}
            </div>

            <Modal show={confirmingDelete} onClose={() => setConfirmingDelete(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Delete {project.key}?
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        This cannot be undone.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setConfirmingDelete(false)}>Cancel</SecondaryButton>
                        <DangerButton
                            onClick={() =>
                                router.delete(route('projects.destroy', project.key), {
                                    data: { confirm: true },
                                })
                            }
                        >
                            Delete
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
