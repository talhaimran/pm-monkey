import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import { selectClass } from '@/Components/IssueFormModal';
import PrimaryButton from '@/Components/PrimaryButton';
import ProjectNav from '@/Components/ProjectNav';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Invitation, Label, Project, ProjectRole } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Settings({
    project,
    invitations,
    roles,
    labels,
    columns,
    can,
}: {
    project: Project;
    invitations: Invitation[];
    roles: ProjectRole[];
    labels: Label[];
    columns: { id: number; name: string; position: number; issue_count: number }[];
    can: { manage: boolean; contribute: boolean };
}) {
    const { errors: pageErrors } = usePage().props as { errors: Record<string, string> };
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const details = useForm({
        name: project.name,
        description: project.description ?? '',
    });
    const invite = useForm({ email: '', role: 'member' as ProjectRole });
    const newLabel = useForm({ name: '', color: '#6b7280' });
    const newColumn = useForm({ name: '' });
    const [moveTargets, setMoveTargets] = useState<Record<number, string>>({});
    const columnRoute = (name: string, id: number) => route(name, [project.key, id]);

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
                <ProjectNav projectKey={project.key} name={project.name} />
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
                        <h3 className={heading}>Board columns</h3>
                        <InputError className="mt-2" message={pageErrors.move_issues_to ?? pageErrors.column} />
                        <ol className="mt-4 space-y-2">
                            {columns.map((c, i) => (
                                <li key={c.id} className="flex flex-wrap items-center gap-2 text-sm">
                                    <TextInput
                                        aria-label={`Name of column ${c.name}`}
                                        className="w-44"
                                        defaultValue={c.name}
                                        onBlur={(e) =>
                                            e.target.value.trim() &&
                                            e.target.value !== c.name &&
                                            router.patch(columnRoute('projects.columns.update', c.id), { name: e.target.value }, { preserveScroll: true })
                                        }
                                    />
                                    <SecondaryButton
                                        aria-label={`Move ${c.name} left`}
                                        disabled={i === 0}
                                        onClick={() => router.patch(columnRoute('projects.columns.update', c.id), { position: i - 1 }, { preserveScroll: true })}
                                    >
                                        ↑
                                    </SecondaryButton>
                                    <SecondaryButton
                                        aria-label={`Move ${c.name} right`}
                                        disabled={i === columns.length - 1}
                                        onClick={() => router.patch(columnRoute('projects.columns.update', c.id), { position: i + 1 }, { preserveScroll: true })}
                                    >
                                        ↓
                                    </SecondaryButton>
                                    <span className="text-gray-500 dark:text-gray-400">{c.issue_count} issues</span>
                                    {c.issue_count > 0 && (
                                        <select
                                            aria-label={`Move ${c.name} issues to`}
                                            className={selectClass}
                                            value={moveTargets[c.id] ?? ''}
                                            onChange={(e) => setMoveTargets({ ...moveTargets, [c.id]: e.target.value })}
                                        >
                                            <option value="">Move issues to…</option>
                                            {columns.filter((o) => o.id !== c.id).map((o) => (
                                                <option key={o.id}>{o.name}</option>
                                            ))}
                                        </select>
                                    )}
                                    <DangerButton
                                        disabled={columns.length === 1 || (c.issue_count > 0 && !moveTargets[c.id])}
                                        onClick={() =>
                                            router.delete(columnRoute('projects.columns.destroy', c.id), {
                                                data: { move_issues_to: moveTargets[c.id] || null },
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Delete
                                    </DangerButton>
                                </li>
                            ))}
                        </ol>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                newColumn.post(route('projects.columns.store', project.key), {
                                    preserveScroll: true,
                                    onSuccess: () => newColumn.reset(),
                                });
                            }}
                            className="mt-4 flex flex-wrap items-start gap-3"
                        >
                            <div className="flex-1">
                                <TextInput
                                    aria-label="New column name"
                                    className="block w-full"
                                    placeholder="Blocked"
                                    value={newColumn.data.name}
                                    onChange={(e) => newColumn.setData('name', e.target.value)}
                                    required
                                />
                                <InputError className="mt-2" message={newColumn.errors.name} />
                            </div>
                            <PrimaryButton disabled={newColumn.processing}>Add column</PrimaryButton>
                        </form>
                    </section>
                )}

                <section className={card}>
                    <h3 className={heading}>Labels</h3>
                    <InputError className="mt-2" message={pageErrors.name} />
                    <ul className="mt-4 flex flex-wrap gap-2">
                        {labels.length === 0 && <li className="text-sm text-gray-500">No labels yet.</li>}
                        {labels.map((l) => (
                            <li key={l.id} className="flex items-center gap-2 rounded-full border px-3 py-1 text-sm text-gray-800 dark:text-gray-200" style={{ borderColor: l.color }}>
                                {can.contribute && (
                                    <input
                                        type="color"
                                        aria-label={`Color for ${l.name}`}
                                        className="h-4 w-4 cursor-pointer border-0 bg-transparent p-0"
                                        defaultValue={l.color}
                                        onBlur={(e) =>
                                            e.target.value !== l.color &&
                                            router.patch(route('projects.labels.update', [project.key, l.id]), { color: e.target.value }, { preserveScroll: true })
                                        }
                                    />
                                )}
                                {l.name}
                                {can.contribute && (
                                    <button
                                        aria-label={`Delete label ${l.name}`}
                                        className="text-gray-400 hover:text-red-600"
                                        onClick={() => router.delete(route('projects.labels.destroy', [project.key, l.id]), { preserveScroll: true })}
                                    >
                                        ×
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                    {can.contribute && (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                newLabel.post(route('projects.labels.store', project.key), {
                                    preserveScroll: true,
                                    onSuccess: () => newLabel.reset('name'),
                                });
                            }}
                            className="mt-4 flex flex-wrap items-start gap-3"
                        >
                            <input
                                type="color"
                                aria-label="New label color"
                                className="h-10 w-10 cursor-pointer rounded border-gray-300 dark:border-gray-700"
                                value={newLabel.data.color}
                                onChange={(e) => newLabel.setData('color', e.target.value)}
                            />
                            <div className="flex-1">
                                <TextInput
                                    aria-label="New label name"
                                    className="block w-full"
                                    placeholder="frontend"
                                    value={newLabel.data.name}
                                    onChange={(e) => newLabel.setData('name', e.target.value)}
                                    required
                                />
                                <InputError className="mt-2" message={newLabel.errors.name} />
                            </div>
                            <PrimaryButton disabled={newLabel.processing}>Add label</PrimaryButton>
                        </form>
                    )}
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
