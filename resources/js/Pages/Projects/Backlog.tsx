import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import IssueFormModal, { selectClass } from '@/Components/IssueFormModal';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { placement, useLaneDroppable, useSortableLanes } from '@/Components/useSortableLanes';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { IssueOptions, Project } from '@/types';
import { closestCorners, DndContext, DragOverlay } from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Card } from './Board';

interface SprintSection {
    id: number;
    name: string;
    goal: string | null;
    state: 'planned' | 'active' | 'completed';
    start_date: string | null;
    end_date: string | null;
    issue_count: number;
    points_total: number;
    points_done: number;
    issues: Card[];
}

interface BacklogData {
    sprints: SprintSection[];
    backlog: { issues: Card[] };
    truncated: boolean;
}

// "backlog" is a reserved sprint name, so it can't collide with a sprint lane.
const BACKLOG = 'backlog';

export default function Backlog({
    project,
    backlog,
    options,
    can,
}: {
    project: Project;
    backlog: BacklogData;
    options: IssueOptions;
    can: { contribute: boolean };
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const [creatingIssue, setCreatingIssue] = useState(false);
    const [editing, setEditing] = useState<SprintSection | 'new' | null>(null);
    const [completing, setCompleting] = useState<SprintSection | null>(null);

    const source = useMemo(
        () => ({ ...Object.fromEntries(backlog.sprints.map((s) => [s.name, s.issues])), [BACKLOG]: backlog.backlog.issues }),
        [backlog],
    );
    const { lanes, active, sensors, reset, handlers } = useSortableLanes(source, (key, lane, laneChanged, index, cards) =>
        router.post(
            route('issues.rank', key),
            { ...(laneChanged ? { sprint: lane } : {}), ...placement(index, cards) },
            { preserveScroll: true, preserveState: true, onError: reset },
        ),
    );

    const hasActive = backlog.sprints.some((s) => s.state === 'active');
    const sprintRoute = (name: string, sprint: SprintSection) => route(name, [project.key, sprint.id]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        <span className="font-mono text-indigo-600 dark:text-indigo-400">{project.key}</span> Backlog
                    </h2>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('projects.show', project.key)}
                            className="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Board
                        </Link>
                        {can.contribute && <SecondaryButton onClick={() => setEditing('new')}>Create sprint</SecondaryButton>}
                        {can.contribute && <PrimaryButton onClick={() => setCreatingIssue(true)}>Create issue</PrimaryButton>}
                    </div>
                </div>
            }
        >
            <Head title={`${project.key} backlog`} />

            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <InputError message={errors.sprint ?? errors.after} />
                {backlog.truncated && (
                    <p className="rounded bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                        Showing the first 1000 issues.
                    </p>
                )}

                <DndContext sensors={sensors} collisionDetection={closestCorners} {...handlers}>
                    {backlog.sprints.map((sprint) => (
                        <Section
                            key={sprint.id}
                            lane={sprint.name}
                            cards={lanes[sprint.name] ?? []}
                            draggable={can.contribute}
                            header={
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{sprint.name}</h3>
                                    <span
                                        className={`rounded px-1.5 text-xs uppercase ${sprint.state === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'}`}
                                    >
                                        {sprint.state}
                                    </span>
                                    {sprint.start_date && (
                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                            {sprint.start_date} → {sprint.end_date}
                                        </span>
                                    )}
                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                        {sprint.issue_count} {sprint.issue_count === 1 ? 'issue' : 'issues'} · {sprint.points_done}/{sprint.points_total} pts done
                                    </span>
                                    {can.contribute && (
                                        <span className="ml-auto flex gap-2">
                                            <SecondaryButton onClick={() => setEditing(sprint)}>Edit</SecondaryButton>
                                            {sprint.state === 'planned' && (
                                                <>
                                                    <PrimaryButton
                                                        disabled={hasActive}
                                                        title={hasActive ? 'Complete the active sprint first' : undefined}
                                                        onClick={() => router.post(sprintRoute('projects.sprints.start', sprint), {}, { preserveScroll: true })}
                                                    >
                                                        Start
                                                    </PrimaryButton>
                                                    <DangerButton onClick={() => router.delete(sprintRoute('projects.sprints.destroy', sprint), { preserveScroll: true })}>
                                                        Delete
                                                    </DangerButton>
                                                </>
                                            )}
                                            {sprint.state === 'active' && <PrimaryButton onClick={() => setCompleting(sprint)}>Complete</PrimaryButton>}
                                        </span>
                                    )}
                                    {sprint.goal && <p className="w-full text-sm text-gray-600 dark:text-gray-400">{sprint.goal}</p>}
                                </div>
                            }
                        />
                    ))}

                    <Section
                        lane={BACKLOG}
                        cards={lanes[BACKLOG] ?? []}
                        draggable={can.contribute}
                        header={
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                Backlog <span className="text-sm font-normal text-gray-500">({(lanes[BACKLOG] ?? []).length})</span>
                            </h3>
                        }
                    />
                    <DragOverlay>{active ? <Row card={active} dragging /> : null}</DragOverlay>
                </DndContext>
            </div>

            {can.contribute && (
                <IssueFormModal show={creatingIssue} onClose={() => setCreatingIssue(false)} projectKey={project.key} options={options} />
            )}
            <SprintFormModal project={project} sprint={editing} onClose={() => setEditing(null)} />
            <CompleteSprintModal
                project={project}
                sprint={completing}
                planned={backlog.sprints.filter((s) => s.state === 'planned').map((s) => s.name)}
                onClose={() => setCompleting(null)}
            />
        </AuthenticatedLayout>
    );
}

function Section({ lane, cards, header, draggable }: { lane: string; cards: Card[]; header: React.ReactNode; draggable: boolean }) {
    const { setNodeRef, isOver } = useLaneDroppable(lane);

    return (
        <section
            ref={setNodeRef}
            aria-label={lane === BACKLOG ? 'Backlog' : `Sprint ${lane}`}
            className={`rounded-lg bg-white p-3 shadow-sm sm:p-4 dark:bg-gray-800 ${isOver ? 'ring-2 ring-indigo-400' : ''}`}
        >
            {header}
            <SortableContext items={cards.map((c) => c.key)} strategy={verticalListSortingStrategy}>
                <ul className="mt-3 min-h-12 divide-y divide-gray-100 rounded border border-dashed border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    {cards.length === 0 && <li className="p-3 text-sm text-gray-400">Drag issues here.</li>}
                    {cards.map((card) => (
                        <SortableRow key={card.key} card={card} disabled={!draggable} />
                    ))}
                </ul>
            </SortableContext>
        </section>
    );
}

function SortableRow({ card, disabled }: { card: Card; disabled: boolean }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: card.key, disabled });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={isDragging ? 'opacity-40' : ''}
            {...attributes}
            {...listeners}
        >
            <Row card={card} />
        </li>
    );
}

function Row({ card, dragging = false }: { card: Card; dragging?: boolean }) {
    return (
        <div
            className={`flex items-center gap-3 bg-white px-3 py-2 text-sm dark:bg-gray-800 ${dragging ? 'rounded shadow-lg ring-2 ring-indigo-400' : ''}`}
        >
            <span className="w-8 text-xs uppercase text-gray-500">{card.type.slice(0, 4)}</span>
            <Link href={route('issues.show', card.key)} className="font-mono text-indigo-600 hover:underline dark:text-indigo-400">
                {card.key}
            </Link>
            <span className="min-w-0 flex-1 truncate text-gray-900 dark:text-gray-100">{card.title}</span>
            <span className="hidden rounded bg-gray-100 px-2 text-xs text-gray-600 sm:inline dark:bg-gray-700 dark:text-gray-300">{card.status}</span>
            <span className="hidden w-28 truncate text-xs text-gray-500 sm:inline dark:text-gray-400">{card.assignee?.name ?? 'Unassigned'}</span>
            <span className="w-6 text-right text-xs text-gray-500" title="Story points">
                {card.story_points ?? '–'}
            </span>
        </div>
    );
}

function SprintFormModal({ project, sprint, onClose }: { project: Project; sprint: SprintSection | 'new' | null; onClose: () => void }) {
    const existing = sprint && sprint !== 'new' ? sprint : null;
    const form = useForm({ name: '', goal: '', start_date: '', end_date: '' });

    // Reset the fields each time the dialog opens for a different sprint.
    const [openedFor, setOpenedFor] = useState<typeof sprint>(null);
    if (sprint !== openedFor) {
        setOpenedFor(sprint);
        form.setData({
            name: existing?.name ?? '',
            goal: existing?.goal ?? '',
            start_date: existing?.start_date ?? '',
            end_date: existing?.end_date ?? '',
        });
        form.clearErrors();
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        const done = { preserveScroll: true, onSuccess: onClose };
        if (existing) form.patch(route('projects.sprints.update', [project.key, existing.id]), done);
        else form.post(route('projects.sprints.store', project.key), done);
    };

    return (
        <Modal show={sprint !== null} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">{existing ? `Edit ${existing.name}` : 'New sprint'}</h2>
                <div>
                    <InputLabel htmlFor="sprint-name" value="Name" />
                    <TextInput
                        id="sprint-name"
                        className="mt-1 block w-full"
                        placeholder={existing ? '' : 'Leave empty for an automatic name'}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <InputError className="mt-1" message={form.errors.name} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="sprint-start" value="Start date" />
                        <TextInput id="sprint-start" type="date" className="mt-1 block w-full" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                        <InputError className="mt-1" message={form.errors.start_date} />
                    </div>
                    <div>
                        <InputLabel htmlFor="sprint-end" value="End date" />
                        <TextInput id="sprint-end" type="date" className="mt-1 block w-full" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                        <InputError className="mt-1" message={form.errors.end_date} />
                    </div>
                </div>
                <div>
                    <InputLabel htmlFor="sprint-goal" value="Goal" />
                    <textarea id="sprint-goal" rows={3} className={`${selectClass} mt-1 w-full`} value={form.data.goal} onChange={(e) => form.setData('goal', e.target.value)} />
                    <InputError className="mt-1" message={form.errors.goal ?? (form.errors as Record<string, string>).sprint} />
                </div>
                <div className="flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <PrimaryButton disabled={form.processing}>{existing ? 'Save' : 'Create'}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

function CompleteSprintModal({
    project,
    sprint,
    planned,
    onClose,
}: {
    project: Project;
    sprint: SprintSection | null;
    planned: string[];
    onClose: () => void;
}) {
    const form = useForm({ confirm: true, move_open_issues_to: BACKLOG });

    return (
        <Modal show={sprint !== null} onClose={onClose}>
            {sprint && (
                <form
                    className="space-y-4 p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('projects.sprints.complete', [project.key, sprint.id]), { preserveScroll: true, onSuccess: onClose });
                    }}
                >
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">Complete {sprint.name}?</h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Done issues stay with the sprint. Unfinished issues move to the destination below. A completed sprint can't be reopened.
                    </p>
                    <div>
                        <InputLabel htmlFor="move-to" value="Move unfinished issues to" />
                        <select
                            id="move-to"
                            className={`${selectClass} mt-1 w-full`}
                            value={form.data.move_open_issues_to}
                            onChange={(e) => form.setData('move_open_issues_to', e.target.value)}
                        >
                            <option value={BACKLOG}>Backlog</option>
                            {planned.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-1" message={form.errors.move_open_issues_to ?? (form.errors as Record<string, string>).sprint} />
                    </div>
                    <div className="flex justify-end gap-3">
                        <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                        <PrimaryButton disabled={form.processing}>Complete sprint</PrimaryButton>
                    </div>
                </form>
            )}
        </Modal>
    );
}
