import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import IssueFormModal, { selectClass } from '@/Components/IssueFormModal';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ActivityEntry, Comment, Issue, IssueLink, IssueOptions } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

// Minimal Markdown styling without @tailwindcss/typography.
const proseClass =
    'text-sm text-gray-800 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold [&_blockquote]:border-l-4 [&_blockquote]:pl-3 dark:text-gray-200 [&_a]:text-indigo-600 [&_code]:rounded [&_code]:bg-gray-100 [&_code]:px-1 dark:[&_code]:bg-gray-700 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2 [&_pre]:overflow-x-auto [&_ul]:list-disc [&_ul]:pl-5';

export default function Show({
    issue,
    activity,
    options,
    can,
}: {
    issue: Issue;
    activity: ActivityEntry[];
    options: IssueOptions;
    can: { contribute: boolean; manage: boolean };
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const [editingTitle, setEditingTitle] = useState(false);
    const [title, setTitle] = useState(issue.title);
    const [editingDescription, setEditingDescription] = useState(false);
    const [description, setDescription] = useState(issue.description ?? '');
    const [addingSubtask, setAddingSubtask] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [tab, setTab] = useState<'comments' | 'activity'>('comments');
    const comment = useForm({ body: '' });

    const save = (data: Record<string, unknown>, onSuccess?: () => void) =>
        router.patch(route('issues.update', issue.key), data as never, { preserveScroll: true, onSuccess });

    const linkList = (title: string, items: IssueLink[] | undefined) =>
        items && items.length > 0 ? (
            <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</h3>
                <ul className="mt-2 divide-y divide-gray-100 rounded border border-gray-100 dark:divide-gray-700 dark:border-gray-700">
                    {items.map((s) => (
                        <li key={s.key}>
                            <Link href={route('issues.show', s.key)} className="flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                <span className="font-mono text-indigo-600 dark:text-indigo-400">{s.key}</span>
                                <span className="flex-1 truncate text-gray-900 dark:text-gray-100">{s.title}</span>
                                <span className="text-xs text-gray-500">{s.status}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        ) : null;

    const sidebarField = (label: string, children: ReactNode, error?: string) => (
        <div>
            <dt className="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{children}</dd>
            <InputError className="mt-1" message={error} />
        </div>
    );

    const picker = (field: string, value: string, choices: { value: string; label: string }[]) => (
        <select
            aria-label={field}
            className={`${selectClass} w-full`}
            value={value}
            disabled={!can.contribute}
            onChange={(e) => save({ [field]: e.target.value === '' ? null : e.target.value })}
        >
            {choices.map((c) => (
                <option key={c.value} value={c.value}>
                    {c.label}
                </option>
            ))}
        </select>
    );

    const plain = (values: string[]) => values.map((v) => ({ value: v, label: v }));

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link href={route('projects.show', issue.project)} className="font-mono hover:underline">
                        {issue.project}
                    </Link>
                    {issue.epic && (
                        <>
                            / <Link href={route('issues.show', issue.epic.key)} className="hover:underline">{issue.epic.key}</Link>
                        </>
                    )}
                    {issue.parent && (
                        <>
                            / <Link href={route('issues.show', issue.parent.key)} className="hover:underline">{issue.parent.key}</Link>
                        </>
                    )}
                    / <span className="font-mono text-gray-800 dark:text-gray-200">{issue.key}</span>
                </div>
            }
        >
            <Head title={`${issue.key} ${issue.title}`} />

            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div className="space-y-6 lg:col-span-2">
                    <section className="space-y-4 rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800">
                        {editingTitle ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    save({ title }, () => setEditingTitle(false));
                                }}
                                className="flex gap-2"
                            >
                                <TextInput className="flex-1 text-lg" value={title} onChange={(e) => setTitle(e.target.value)} isFocused required />
                                <PrimaryButton>Save</PrimaryButton>
                            </form>
                        ) : (
                            <h1
                                className={`text-xl font-semibold text-gray-900 dark:text-gray-100 ${can.contribute ? 'cursor-text' : ''}`}
                                onClick={() => can.contribute && setEditingTitle(true)}
                            >
                                {issue.title}
                            </h1>
                        )}
                        <InputError message={errors.title} />

                        <div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h3>
                                {can.contribute && !editingDescription && (
                                    <button className="text-sm text-indigo-600 hover:underline dark:text-indigo-400" onClick={() => setEditingDescription(true)}>
                                        Edit
                                    </button>
                                )}
                            </div>
                            {editingDescription ? (
                                <div className="mt-2 space-y-2">
                                    <textarea rows={8} className={`${selectClass} w-full font-mono`} value={description} onChange={(e) => setDescription(e.target.value)} />
                                    <div className="flex gap-2">
                                        <PrimaryButton onClick={() => save({ description: description || null }, () => setEditingDescription(false))}>
                                            Save
                                        </PrimaryButton>
                                        <SecondaryButton onClick={() => setEditingDescription(false)}>Cancel</SecondaryButton>
                                    </div>
                                </div>
                            ) : issue.description_html ? (
                                <div className={proseClass} dangerouslySetInnerHTML={{ __html: issue.description_html }} />
                            ) : (
                                <p className="mt-2 text-sm italic text-gray-400">No description.</p>
                            )}
                        </div>

                        {linkList('Issues in this epic', issue.epic_issues)}
                        {issue.type !== 'epic' && !issue.parent && (
                            <div className="space-y-2">
                                {linkList('Sub-tasks', issue.subtasks)}
                                {can.contribute && (
                                    <SecondaryButton onClick={() => setAddingSubtask(true)}>Add sub-task</SecondaryButton>
                                )}
                            </div>
                        )}
                    </section>

                    <section className="rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800">
                        <div className="flex gap-4 border-b border-gray-100 text-sm dark:border-gray-700">
                            {(['comments', 'activity'] as const).map((t) => (
                                <button
                                    key={t}
                                    onClick={() => setTab(t)}
                                    className={`-mb-px border-b-2 pb-2 capitalize ${tab === t ? 'border-indigo-500 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500'}`}
                                >
                                    {t}
                                </button>
                            ))}
                        </div>

                        {tab === 'comments' ? (
                            <div className="mt-4 space-y-4">
                                {issue.comments?.map((c) => <CommentItem key={c.id} comment={c} canDelete={!!c.can_edit || can.manage} />)}
                                {can.contribute && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            comment.post(route('issues.comments.store', issue.key), {
                                                preserveScroll: true,
                                                onSuccess: () => comment.reset(),
                                            });
                                        }}
                                        className="space-y-2"
                                    >
                                        <textarea
                                            rows={3}
                                            aria-label="Add a comment"
                                            placeholder="Add a comment (Markdown)…"
                                            className={`${selectClass} w-full`}
                                            value={comment.data.body}
                                            onChange={(e) => comment.setData('body', e.target.value)}
                                        />
                                        <InputError message={comment.errors.body} />
                                        <PrimaryButton disabled={comment.processing || !comment.data.body.trim()}>Comment</PrimaryButton>
                                    </form>
                                )}
                            </div>
                        ) : (
                            <ActivityList entries={activity} />
                        )}
                    </section>
                </div>

                <aside className="space-y-4 rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800">
                    <dl className="space-y-4">
                        {sidebarField('Status', picker('status', issue.status, plain(options.columns)), errors.status)}
                        {sidebarField(
                            'Type',
                            picker('type', issue.type, plain(issue.parent ? options.types.filter((t) => t !== 'epic') : options.types)),
                            errors.type,
                        )}
                        {sidebarField('Priority', picker('priority', issue.priority, plain(options.priorities)), errors.priority)}
                        {sidebarField(
                            'Assignee',
                            picker('assignee', issue.assignee?.email ?? '', [
                                { value: '', label: 'Unassigned' },
                                ...options.members.map((m) => ({ value: m.email, label: m.name })),
                            ]),
                            errors.assignee,
                        )}
                        {sidebarField(
                            'Labels',
                            <div className="flex flex-wrap gap-2">
                                {options.labels.length === 0 && <span className="text-gray-400">No labels in project</span>}
                                {options.labels.map((l) => {
                                    const on = issue.labels.includes(l.name);
                                    return (
                                        <button
                                            key={l.id}
                                            type="button"
                                            disabled={!can.contribute}
                                            onClick={() => save({ labels: on ? issue.labels.filter((n) => n !== l.name) : [...issue.labels, l.name] })}
                                            className={`rounded-full border px-2 py-0.5 text-xs ${on ? 'text-white' : 'text-gray-600 dark:text-gray-300'}`}
                                            style={on ? { backgroundColor: l.color, borderColor: l.color } : { borderColor: l.color }}
                                            aria-pressed={on}
                                        >
                                            {l.name}
                                        </button>
                                    );
                                })}
                            </div>,
                            errors.labels,
                        )}
                        {sidebarField(
                            'Story points',
                            <TextInput
                                type="number"
                                min={0}
                                className="w-full"
                                defaultValue={issue.story_points ?? ''}
                                disabled={!can.contribute}
                                onBlur={(e) => {
                                    const v = e.target.value === '' ? null : Number(e.target.value);
                                    if (v !== issue.story_points) save({ story_points: v });
                                }}
                            />,
                            errors.story_points,
                        )}
                        {sidebarField(
                            'Due date',
                            <TextInput
                                type="date"
                                className="w-full"
                                defaultValue={issue.due_date ?? ''}
                                disabled={!can.contribute}
                                onChange={(e) => save({ due_date: e.target.value || null })}
                            />,
                            errors.due_date,
                        )}
                        {issue.type !== 'epic' &&
                            sidebarField(
                                'Epic',
                                <TextInput
                                    className="w-full font-mono"
                                    placeholder={`${issue.project}-1`}
                                    defaultValue={issue.epic?.key ?? ''}
                                    disabled={!can.contribute}
                                    onBlur={(e) => {
                                        const v = e.target.value.trim() || null;
                                        if (v !== (issue.epic?.key ?? null)) save({ epic: v });
                                    }}
                                />,
                                errors.epic,
                            )}
                        {sidebarField('Reporter', issue.reporter?.name ?? '—')}
                        {sidebarField('Created', new Date(issue.created_at).toLocaleString())}
                    </dl>
                    <InputError message={errors.parent} />
                    {can.contribute && (
                        <DangerButton className="w-full justify-center" onClick={() => setConfirmingDelete(true)}>
                            Delete issue
                        </DangerButton>
                    )}
                </aside>
            </div>

            {can.contribute && (
                <IssueFormModal
                    show={addingSubtask}
                    onClose={() => setAddingSubtask(false)}
                    projectKey={issue.project}
                    options={options}
                    parent={issue.key}
                />
            )}

            <Modal show={confirmingDelete} onClose={() => setConfirmingDelete(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">Delete {issue.key}?</h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        This also deletes its sub-tasks, comments and history. It cannot be undone.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setConfirmingDelete(false)}>Cancel</SecondaryButton>
                        <DangerButton onClick={() => router.delete(route('issues.destroy', issue.key), { data: { confirm: true } })}>
                            Delete
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function CommentItem({ comment, canDelete }: { comment: Comment; canDelete: boolean }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ body: comment.body });

    return (
        <div className="rounded border border-gray-100 p-3 dark:border-gray-700">
            <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>
                    <strong className="text-gray-700 dark:text-gray-300">{comment.author?.name ?? 'Deleted user'}</strong> ·{' '}
                    {new Date(comment.created_at).toLocaleString()}
                </span>
                <span className="flex gap-3">
                    {comment.can_edit && !editing && (
                        <button className="hover:underline" onClick={() => setEditing(true)}>
                            Edit
                        </button>
                    )}
                    {canDelete && (
                        <button
                            className="text-red-600 hover:underline"
                            onClick={() => router.delete(route('comments.destroy', comment.id), { preserveScroll: true })}
                        >
                            Delete
                        </button>
                    )}
                </span>
            </div>
            {editing ? (
                <form
                    className="mt-2 space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(route('comments.update', comment.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                >
                    <textarea rows={3} aria-label="Edit comment" className={`${selectClass} w-full`} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                    <div className="flex gap-2">
                        <PrimaryButton disabled={form.processing}>Save</PrimaryButton>
                        <SecondaryButton onClick={() => setEditing(false)}>Cancel</SecondaryButton>
                    </div>
                </form>
            ) : (
                <div className={`${proseClass} mt-1`} dangerouslySetInnerHTML={{ __html: comment.body_html ?? '' }} />
            )}
        </div>
    );
}

function ActivityList({ entries }: { entries: ActivityEntry[] }) {
    const show = (v: unknown) => (v === null || v === undefined || v === '' ? 'none' : String(v));

    return (
        <ul className="mt-4 space-y-3 text-sm">
            {entries.map((a) => (
                <li key={a.id} className="text-gray-700 dark:text-gray-300">
                    <div className="text-xs text-gray-500 dark:text-gray-400">
                        <strong className="text-gray-700 dark:text-gray-300">{a.user?.name ?? 'Someone'}</strong> {a.action} ·{' '}
                        {new Date(a.created_at).toLocaleString()} ·{' '}
                        <span className="rounded bg-gray-100 px-1 uppercase dark:bg-gray-700">{a.via}</span>
                        {a.token_name && <span> token “{a.token_name}”</span>}
                    </div>
                    {a.changes && (
                        <ul className="mt-1 space-y-0.5 pl-3">
                            {Object.entries(a.changes).map(([field, c]) => (
                                <li key={field}>
                                    <span className="font-medium">{field.replace('_', ' ')}</span>: {show(c.from)} → {show(c.to)}
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ul>
    );
}
