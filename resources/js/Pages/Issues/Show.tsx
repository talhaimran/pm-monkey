import Avatar from '@/Components/Avatar';
import DangerButton from '@/Components/DangerButton';
import Dropdown from '@/Components/Dropdown';
import InputError from '@/Components/InputError';
import IssueFormModal, { selectClass } from '@/Components/IssueFormModal';
import IssueTypeIcon from '@/Components/IssueTypeIcon';
import Modal from '@/Components/Modal';
import PriorityIcon from '@/Components/PriorityIcon';
import PrimaryButton from '@/Components/PrimaryButton';
import { ProjectHeader } from '@/Components/ProjectNav';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusLozenge, { lozengeClass } from '@/Components/StatusLozenge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ActivityEntry, Comment, Issue, IssueLink, IssueOptions } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

// Minimal Markdown styling without @tailwindcss/typography.
const proseClass =
    'break-words text-ink [&_a]:text-brand [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-line [&_blockquote]:pl-3 [&_code]:rounded [&_code]:bg-neutral [&_code]:px-1 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:bg-sunken [&_pre]:p-2 [&_ul]:list-disc [&_ul]:pl-5';

// Jira-style inline field: looks like text until hovered or focused.
const inlineField = `${selectClass} w-full !border-transparent !bg-transparent hover:!bg-neutral focus:!border-brand focus:!bg-surface disabled:hover:!bg-transparent`;

export default function Show({
    issue,
    project,
    activity,
    options,
    can,
}: {
    issue: Issue;
    project: { key: string; name: string };
    activity: ActivityEntry[];
    options: IssueOptions;
    can: { contribute: boolean; manage: boolean };
}) {
    const page = usePage();
    const errors = page.props.errors as Record<string, string>;
    const me = page.props.auth.user;
    const [editingTitle, setEditingTitle] = useState(false);
    const [title, setTitle] = useState(issue.title);
    const [editingDescription, setEditingDescription] = useState(false);
    const [description, setDescription] = useState(issue.description ?? '');
    const [addingSubtask, setAddingSubtask] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [tab, setTab] = useState<'comments' | 'history'>('comments');
    const comment = useForm({ body: '' });

    const save = (data: Record<string, unknown>, onSuccess?: () => void) =>
        router.patch(route('issues.update', issue.key), data as never, { preserveScroll: true, onSuccess });

    const linkList = (heading: string, items: IssueLink[] | undefined, subtasks = false) =>
        items && items.length > 0 ? (
            <section>
                <h2 className="mb-2 font-semibold">{heading}</h2>
                <ul className="divide-y divide-line rounded border border-line">
                    {items.map((s) => (
                        <li key={s.key}>
                            <Link href={route('issues.show', s.key)} className="flex items-center gap-2 px-3 py-2 hover:bg-sunken">
                                <IssueTypeIcon type={s.type ?? 'task'} subtask={subtasks} />
                                <span className="shrink-0 text-ink-subtle">{s.key}</span>
                                <span className="min-w-0 flex-1 truncate">{s.title}</span>
                                {s.status && <StatusLozenge status={s.status} columns={options.columns} />}
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>
        ) : null;

    const field = (label: string, children: ReactNode, error?: string) => (
        <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] items-start gap-2 py-1">
            <dt className="pt-2 text-xs font-semibold text-ink-subtle">{label}</dt>
            <dd className="min-h-8">
                {children}
                <InputError className="mt-1" message={error} />
            </dd>
        </div>
    );

    const picker = (name: string, value: string, choices: { value: string; label: string }[], className = inlineField) => (
        <select
            aria-label={name}
            className={className}
            value={value}
            disabled={!can.contribute}
            onChange={(e) => save({ [name]: e.target.value === '' ? null : e.target.value })}
        >
            {choices.map((c) => (
                <option key={c.value} value={c.value}>
                    {c.label}
                </option>
            ))}
        </select>
    );

    const plain = (values: string[]) => values.map((v) => ({ value: v, label: v }));
    const crumbs = [issue.epic, issue.parent].filter((l): l is IssueLink => !!l).map((l) => ({ label: l.key, href: route('issues.show', l.key) }));

    return (
        <AuthenticatedLayout
            project={project}
            header={<ProjectHeader project={project} crumbs={[...crumbs, { label: issue.key, href: route('issues.show', issue.key) }]} />}
        >
            <Head title={`${issue.key} ${issue.title}`} />

            <div className="grid gap-8 px-4 pb-12 pt-2 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="min-w-0 space-y-6">
                    <div>
                        {editingTitle ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    save({ title }, () => setEditingTitle(false));
                                }}
                                className="flex gap-2"
                            >
                                <TextInput
                                    aria-label="Summary"
                                    className="flex-1 text-xl font-semibold"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Escape' && setEditingTitle(false)}
                                    isFocused
                                    required
                                />
                                <PrimaryButton>Save</PrimaryButton>
                                <SecondaryButton onClick={() => setEditingTitle(false)}>Cancel</SecondaryButton>
                            </form>
                        ) : (
                            <h1 className="break-words text-2xl font-semibold">
                                {can.contribute ? (
                                    <button type="button" className="-mx-1 w-full rounded px-1 text-start hover:bg-neutral" onClick={() => setEditingTitle(true)} title="Edit summary">
                                        {issue.title}
                                    </button>
                                ) : (
                                    issue.title
                                )}
                            </h1>
                        )}
                        <InputError message={errors.title} />
                        {can.contribute && issue.type !== 'epic' && !issue.parent && (
                            <SecondaryButton className="mt-3" onClick={() => setAddingSubtask(true)}>
                                + Add sub-task
                            </SecondaryButton>
                        )}
                    </div>

                    <section>
                        <h2 className="mb-1 font-semibold">Description</h2>
                        {editingDescription ? (
                            <div className="space-y-2">
                                <textarea
                                    rows={8}
                                    aria-label="Description"
                                    className={`${selectClass} w-full font-mono`}
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                />
                                <div className="flex gap-2">
                                    <PrimaryButton onClick={() => save({ description: description || null }, () => setEditingDescription(false))}>Save</PrimaryButton>
                                    <SecondaryButton onClick={() => setEditingDescription(false)}>Cancel</SecondaryButton>
                                </div>
                            </div>
                        ) : (
                            <div
                                className={`-mx-2 rounded px-2 py-1 ${can.contribute ? 'cursor-text hover:bg-sunken' : ''}`}
                                onClick={() => can.contribute && setEditingDescription(true)}
                            >
                                {/* description_html is rendered server-side with html_input=strip and no unsafe links. */}
                                {issue.description_html ? (
                                    <div className={proseClass} dangerouslySetInnerHTML={{ __html: issue.description_html }} />
                                ) : (
                                    <p className="text-ink-subtle">{can.contribute ? 'Add a description…' : 'No description.'}</p>
                                )}
                            </div>
                        )}
                    </section>

                    {linkList('Issues in this epic', issue.epic_issues)}
                    {linkList('Child issues', issue.subtasks, true)}

                    <section>
                        <h2 className="mb-2 font-semibold">Activity</h2>
                        <div className="mb-4 flex items-center gap-1" role="tablist" aria-label="Activity">
                            <span className="me-1 text-ink-subtle">Show:</span>
                            {(['comments', 'history'] as const).map((t) => (
                                <button
                                    key={t}
                                    role="tab"
                                    aria-selected={tab === t}
                                    onClick={() => setTab(t)}
                                    className={`h-7 rounded-[3px] px-2 font-medium ${tab === t ? 'bg-selected text-brand' : 'bg-neutral text-ink hover:bg-neutral-hover'}`}
                                >
                                    {t === 'comments' ? 'Comments' : 'History'}
                                </button>
                            ))}
                        </div>

                        {tab === 'comments' ? (
                            <div className="space-y-5">
                                {can.contribute && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            comment.post(route('issues.comments.store', issue.key), {
                                                preserveScroll: true,
                                                onSuccess: () => comment.reset(),
                                            });
                                        }}
                                        className="flex gap-3"
                                    >
                                        <Avatar name={me.name} email={me.email} size="md" />
                                        <div className="flex-1 space-y-2">
                                            <textarea
                                                rows={comment.data.body ? 4 : 1}
                                                aria-label="Add a comment"
                                                placeholder="Add a comment… (Markdown)"
                                                className={`${selectClass} w-full`}
                                                value={comment.data.body}
                                                onChange={(e) => comment.setData('body', e.target.value)}
                                            />
                                            <InputError message={comment.errors.body} />
                                            {comment.data.body && (
                                                <div className="flex gap-2">
                                                    <PrimaryButton disabled={comment.processing || !comment.data.body.trim()}>Save</PrimaryButton>
                                                    <SecondaryButton onClick={() => comment.reset()}>Cancel</SecondaryButton>
                                                </div>
                                            )}
                                        </div>
                                    </form>
                                )}
                                {issue.comments?.length === 0 && !can.contribute && <p className="text-ink-subtle">No comments yet.</p>}
                                {issue.comments?.map((c) => <CommentItem key={c.id} comment={c} canDelete={!!c.can_edit || can.manage} />)}
                            </div>
                        ) : (
                            <ActivityList entries={activity} />
                        )}
                    </section>
                </div>

                <aside className="space-y-4 lg:pt-1">
                    <div className="flex items-center gap-2">
                        {picker(
                            'status',
                            issue.status,
                            plain(options.columns),
                            `rounded-[3px] border-0 py-1.5 ps-3 pe-8 text-sm font-semibold focus:ring-2 focus:ring-brand disabled:opacity-100 ${lozengeClass(issue.status, options.columns)}`,
                        )}
                        {can.contribute && (
                            <div className="ms-auto">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button type="button" aria-label="More actions" className="flex h-8 w-8 items-center justify-center rounded-[3px] text-ink-subtle hover:bg-neutral">
                                            <svg viewBox="0 0 16 16" className="h-4 w-4" aria-hidden="true">
                                                <circle cx="3" cy="8" r="1.5" fill="currentColor" />
                                                <circle cx="8" cy="8" r="1.5" fill="currentColor" />
                                                <circle cx="13" cy="8" r="1.5" fill="currentColor" />
                                            </svg>
                                        </button>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content>
                                        <button
                                            type="button"
                                            className="block w-full border-l-2 border-transparent px-4 py-2 text-start text-danger hover:border-danger hover:bg-sunken"
                                            onClick={() => setConfirmingDelete(true)}
                                        >
                                            Delete
                                        </button>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        )}
                    </div>
                    <InputError message={errors.status} />

                    <section className="rounded border border-line">
                        <h2 className="border-b border-line px-4 py-3 font-semibold">Details</h2>
                        <dl className="px-4 py-2">
                            {field(
                                'Assignee',
                                <div className="flex items-center gap-1">
                                    {issue.assignee && <Avatar name={issue.assignee.name} email={issue.assignee.email} />}
                                    {picker('assignee', issue.assignee?.email ?? '', [
                                        { value: '', label: 'Unassigned' },
                                        ...options.members.map((m) => ({ value: m.email, label: m.name })),
                                    ])}
                                </div>,
                                errors.assignee,
                            )}
                            {field(
                                'Reporter',
                                issue.reporter ? (
                                    <span className="flex items-center gap-2 pt-1">
                                        <Avatar name={issue.reporter.name} email={issue.reporter.email} />
                                        {issue.reporter.name}
                                    </span>
                                ) : (
                                    <span className="block pt-2 text-ink-subtle">None</span>
                                ),
                            )}
                            {field(
                                'Labels',
                                <div className="flex flex-wrap gap-1 pt-1.5">
                                    {options.labels.length === 0 && <span className="text-ink-subtle">None</span>}
                                    {options.labels.map((l) => {
                                        const on = issue.labels.includes(l.name);
                                        return (
                                            <button
                                                key={l.id}
                                                type="button"
                                                disabled={!can.contribute}
                                                onClick={() => save({ labels: on ? issue.labels.filter((n) => n !== l.name) : [...issue.labels, l.name] })}
                                                className={`max-w-full truncate rounded-[3px] border px-1.5 text-xs ${on ? 'border-transparent bg-neutral-hover text-ink' : 'border-dashed border-line text-ink-subtle hover:text-ink'}`}
                                                aria-pressed={on}
                                                title={on ? `Remove ${l.name}` : `Add ${l.name}`}
                                            >
                                                <span className="me-1 inline-block h-2 w-2 rounded-full" style={{ backgroundColor: l.color }} />
                                                {l.name}
                                            </button>
                                        );
                                    })}
                                </div>,
                                errors.labels,
                            )}
                            {field(
                                'Sprint',
                                picker('sprint', issue.sprint?.state === 'completed' ? '' : (issue.sprint?.name ?? ''), [
                                    { value: '', label: issue.sprint?.state === 'completed' ? `${issue.sprint.name} (completed)` : 'None' },
                                    ...plain(options.sprints),
                                ]),
                                errors.sprint,
                            )}
                            {field(
                                'Story points',
                                <TextInput
                                    type="number"
                                    min={0}
                                    aria-label="Story points"
                                    className={`${inlineField} py-1.5`}
                                    placeholder="None"
                                    defaultValue={issue.story_points ?? ''}
                                    disabled={!can.contribute}
                                    onBlur={(e) => {
                                        const v = e.target.value === '' ? null : Number(e.target.value);
                                        if (v !== issue.story_points) save({ story_points: v });
                                    }}
                                />,
                                errors.story_points,
                            )}
                            {field(
                                'Priority',
                                <div className="flex items-center gap-1">
                                    <PriorityIcon priority={issue.priority} className="ms-1" />
                                    {picker('priority', issue.priority, plain(options.priorities))}
                                </div>,
                                errors.priority,
                            )}
                            {field(
                                'Due date',
                                <TextInput
                                    type="date"
                                    aria-label="Due date"
                                    className={`${inlineField} py-1.5`}
                                    defaultValue={issue.due_date ?? ''}
                                    disabled={!can.contribute}
                                    onChange={(e) => save({ due_date: e.target.value || null })}
                                />,
                                errors.due_date,
                            )}
                            {issue.type !== 'epic' &&
                                field(
                                    issue.parent ? 'Parent' : 'Epic',
                                    issue.parent ? (
                                        <Link href={route('issues.show', issue.parent.key)} className="block truncate pt-2 text-brand hover:underline">
                                            {issue.parent.key} {issue.parent.title}
                                        </Link>
                                    ) : (
                                        <TextInput
                                            aria-label="Epic key"
                                            className={`${inlineField} py-1.5`}
                                            placeholder={`None (e.g. ${issue.project}-1)`}
                                            defaultValue={issue.epic?.key ?? ''}
                                            disabled={!can.contribute}
                                            onBlur={(e) => {
                                                const v = e.target.value.trim() || null;
                                                if (v !== (issue.epic?.key ?? null)) save({ epic: v });
                                            }}
                                        />
                                    ),
                                    errors.epic ?? errors.parent,
                                )}
                            {field(
                                'Type',
                                <div className="flex items-center gap-1">
                                    <IssueTypeIcon type={issue.type} subtask={!!issue.parent} className="ms-1" />
                                    {picker('type', issue.type, plain(issue.parent ? options.types.filter((t) => t !== 'epic') : options.types))}
                                </div>,
                                errors.type,
                            )}
                        </dl>
                    </section>

                    <p className="text-xs text-ink-subtle">
                        Created {new Date(issue.created_at).toLocaleString()}
                        <br />
                        Updated {new Date(issue.updated_at).toLocaleString()}
                    </p>
                </aside>
            </div>

            {can.contribute && (
                <IssueFormModal show={addingSubtask} onClose={() => setAddingSubtask(false)} projectKey={issue.project} options={options} parent={issue.key} />
            )}

            <Modal show={confirmingDelete} onClose={() => setConfirmingDelete(false)}>
                <div className="p-6">
                    <h2 className="text-xl font-semibold">Delete {issue.key}?</h2>
                    <p className="mt-2 text-ink-subtle">This also deletes its sub-tasks, comments and history. It cannot be undone.</p>
                    <div className="mt-6 flex justify-end gap-2">
                        <SecondaryButton onClick={() => setConfirmingDelete(false)}>Cancel</SecondaryButton>
                        <DangerButton onClick={() => router.delete(route('issues.destroy', issue.key), { data: { confirm: true } })}>Delete</DangerButton>
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
        <div className="flex gap-3">
            {comment.author ? <Avatar name={comment.author.name} email={comment.author.email} size="md" /> : <span className="h-8 w-8 shrink-0 rounded-full bg-neutral" />}
            <div className="min-w-0 flex-1">
                <div className="text-ink-subtle">
                    <strong className="font-semibold text-ink">{comment.author?.name ?? 'Deleted user'}</strong>{' '}
                    <span className="text-xs">{new Date(comment.created_at).toLocaleString()}</span>
                </div>
                {editing ? (
                    <form
                        className="mt-1 space-y-2"
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
                    // body_html is rendered server-side with html_input=strip and no unsafe links.
                    <div className={proseClass} dangerouslySetInnerHTML={{ __html: comment.body_html ?? '' }} />
                )}
                {!editing && (comment.can_edit || canDelete) && (
                    <div className="flex gap-3 text-xs font-medium text-ink-subtle">
                        {comment.can_edit && (
                            <button className="hover:underline" onClick={() => setEditing(true)}>
                                Edit
                            </button>
                        )}
                        {canDelete && (
                            <button className="hover:underline" onClick={() => router.delete(route('comments.destroy', comment.id), { preserveScroll: true })}>
                                Delete
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function ActivityList({ entries }: { entries: ActivityEntry[] }) {
    const show = (v: unknown) => (v === null || v === undefined || v === '' ? 'None' : String(v));

    if (entries.length === 0) return <p className="text-ink-subtle">No history yet.</p>;

    return (
        <ul className="space-y-4">
            {entries.map((a) => (
                <li key={a.id} className="flex gap-3">
                    {a.user ? <Avatar name={a.user.name} email={a.user.email} size="md" /> : <span className="h-8 w-8 shrink-0 rounded-full bg-neutral" />}
                    <div className="min-w-0 flex-1">
                        <div className="text-ink-subtle">
                            <strong className="font-semibold text-ink">{a.user?.name ?? 'Someone'}</strong> {a.action}{' '}
                            <span className="text-xs">{new Date(a.created_at).toLocaleString()}</span>
                            {a.via !== 'web' && (
                                <span className="ms-2 inline-block rounded-[3px] bg-loz-progress px-1 text-[11px] font-bold uppercase text-loz-progress-ink">
                                    via {a.via}
                                </span>
                            )}
                            {a.token_name && <span className="ms-1 text-xs">token “{a.token_name}”</span>}
                        </div>
                        {a.changes && (
                            <ul className="mt-1 space-y-0.5">
                                {Object.entries(a.changes).map(([name, c]) => (
                                    <li key={name} className="break-words">
                                        <span className="font-medium capitalize">{name.replace('_', ' ')}</span>: {show(c.from)} → {show(c.to)}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}
