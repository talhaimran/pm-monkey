import { ChevronRight, Ellipsis } from 'lucide-react';
import Avatar from '@/Components/Avatar';
import CompleteSprintModal from '@/Components/CompleteSprintModal';
import Dropdown from '@/Components/Dropdown';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import IssueFormModal, { selectClass } from '@/Components/IssueFormModal';
import IssueTypeIcon from '@/Components/IssueTypeIcon';
import Modal from '@/Components/Modal';
import PriorityIcon from '@/Components/PriorityIcon';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { ProjectHeader } from '@/Components/ProjectNav';
import StatusLozenge from '@/Components/StatusLozenge';
import TextInput from '@/Components/TextInput';
import { useCreateShortcut } from '@/Components/useCreateShortcut';
import { placement, useLaneDroppable, useSortableLanes } from '@/Components/useSortableLanes';
import EmptyState from '@/Components/EmptyState';
import { useWarningConfirm } from '@/Components/WarningConfirm';
import { issueUrl } from '@/issueUrl';
import { transition } from '@/motion';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { IssueOptions, Project } from '@/types';
import { closestCorners, DndContext, DragOverlay } from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
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
    const openCreate = useCallback(() => setCreatingIssue(true), []);
    useCreateShortcut(openCreate, can.contribute);
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

    const [ask, confirmDialog] = useWarningConfirm();
    const hasActive = backlog.sprints.some((s) => s.state === 'active');
    const sprintRoute = (name: string, sprint: SprintSection) => route(name, [project.key, sprint.id]);

    return (
        <AuthenticatedLayout
            project={project}
            header={
                <ProjectHeader project={project} title="Backlog">
                    {can.contribute && <PrimaryButton onClick={openCreate}>Create issue</PrimaryButton>}
                </ProjectHeader>
            }
        >
            <Head title={`${project.key} backlog`} />

            <div className="space-y-4 px-4 pb-10 md:px-8">
                <InputError message={errors.sprint ?? errors.after} />
                {backlog.truncated && (
                    <p className="rounded-ctl tint-warn px-3 py-2">Showing the first 1000 issues.</p>
                )}

                <DndContext sensors={sensors} collisionDetection={closestCorners} {...handlers}>
                    {backlog.sprints.map((sprint) => (
                        <Section
                            key={sprint.id}
                            lane={sprint.name}
                            cards={lanes[sprint.name] ?? []}
                            columns={options.columns}
                            draggable={can.contribute}
                            title={sprint.name}
                            meta={
                                <>
                                    {sprint.start_date && (
                                        <span>
                                            {shortDate(sprint.start_date)} – {sprint.end_date ? shortDate(sprint.end_date) : '…'}
                                        </span>
                                    )}
                                    <span>
                                        {sprint.issue_count} {sprint.issue_count === 1 ? 'issue' : 'issues'} · {sprint.points_total} pts
                                    </span>
                                </>
                            }
                            actions={
                                <>
                                    <span className="hidden h-pill-h items-center rounded-pill tint-good px-2.5 text-xs sm:flex">{sprint.points_done} pts done</span>
                                    {can.contribute && sprint.state === 'planned' && (
                                        <SecondaryButton
                                            disabled={hasActive}
                                            title={hasActive ? 'Complete the active sprint first' : undefined}
                                            onClick={() => router.post(sprintRoute('projects.sprints.start', sprint), {}, { preserveScroll: true })}
                                        >
                                            Start sprint
                                        </SecondaryButton>
                                    )}
                                    {can.contribute && sprint.state === 'active' && (
                                        <SecondaryButton onClick={() => setCompleting(sprint)}>Complete sprint</SecondaryButton>
                                    )}
                                    {can.contribute && (
                                        <Dropdown>
                                            <Dropdown.Trigger>
                                                <button type="button" aria-label={`${sprint.name} actions`} className="flex h-8 w-8 items-center justify-center rounded-ctl text-muted hover:bg-card-2">
                                                    <Ellipsis className="h-4 w-4" aria-hidden="true" />
                                                </button>
                                            </Dropdown.Trigger>
                                            <Dropdown.Content>
                                                <button type="button" className={menuItem} onClick={() => setEditing(sprint)}>
                                                    Edit sprint
                                                </button>
                                                {sprint.state === 'planned' && (
                                                    <button
                                                        type="button"
                                                        className={`${menuItem} text-t-bad`}
                                                        onClick={() =>
                                                            ask({
                                                                title: `Delete ${sprint.name}?`,
                                                                warnings: [
                                                                    { text: `Its ${sprint.issue_count} ${sprint.issue_count === 1 ? 'issue goes' : 'issues go'} back to the backlog.` },
                                                                    { text: "A deleted sprint can't be brought back." },
                                                                ],
                                                                confirmLabel: 'Delete sprint',
                                                                run: () => router.delete(sprintRoute('projects.sprints.destroy', sprint), { preserveScroll: true }),
                                                            })
                                                        }
                                                    >
                                                        Delete sprint
                                                    </button>
                                                )}
                                            </Dropdown.Content>
                                        </Dropdown>
                                    )}
                                </>
                            }
                            goal={sprint.goal}
                        />
                    ))}

                    <Section
                        lane={BACKLOG}
                        cards={lanes[BACKLOG] ?? []}
                        columns={options.columns}
                        draggable={can.contribute}
                        title="Backlog"
                        meta={<span>{(lanes[BACKLOG] ?? []).length} issues</span>}
                        actions={can.contribute && <SecondaryButton onClick={() => setEditing('new')}>Create sprint</SecondaryButton>}
                    />
                    <DragOverlay dropAnimation={css('drop')}>{active ? <Row card={active} columns={options.columns} dragging /> : null}</DragOverlay>
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
            {confirmDialog}
        </AuthenticatedLayout>
    );
}

const shortDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
const menuItem = 'block w-full px-4 py-2 text-start text-md hover:bg-card-2';

// Sortable timings come from motion.ts, so reduced motion turns them off with everything else.
const css = (moment: 'lift' | 'drop') => {
    const t = transition(moment);
    return { duration: t.duration * 1000, easing: `cubic-bezier(${t.ease.join(',')})` };
};

function Section({
    lane,
    cards,
    columns,
    draggable,
    title,
    meta,
    actions,
    goal,
}: {
    lane: string;
    cards: Card[];
    columns: string[];
    draggable: boolean;
    title: string;
    meta: React.ReactNode;
    actions?: React.ReactNode;
    goal?: string | null;
}) {
    const { setNodeRef, isOver } = useLaneDroppable(lane);
    const [open, setOpen] = useState(true);

    return (
        <section
            ref={setNodeRef}
            aria-label={lane === BACKLOG ? 'Backlog' : `Sprint ${lane}`}
            className={`rounded-card border p-3 shadow-xs transition-colors duration-instant ${isOver ? 'border-c-cobalt tint-cobalt' : 'border-transparent bg-card'}`}
        >
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 px-1 py-1">
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                    className="flex min-w-0 items-center gap-1 rounded-ctl px-1 py-1 hover:bg-card-2"
                >
                    <ChevronRight className={`h-4 w-4 shrink-0 text-muted transition-transform ${open ? 'rotate-90' : ''}`} aria-hidden="true" />
                    <h3 className="truncate text-lg text-text">{title}</h3>
                </button>
                <span className="flex flex-wrap gap-2 text-muted">{meta}</span>
                <span className="ms-auto flex items-center gap-2">{actions}</span>
                {goal && <p className="w-full ps-7 text-muted">{goal}</p>}
            </div>
            {open && (
                <SortableContext items={cards.map((c) => c.key)} strategy={verticalListSortingStrategy}>
                    <ul className="mt-2 flex min-h-10 flex-col gap-1">
                        {cards.length === 0 && (
                            <li>
                                <EmptyState
                                    compact
                                    title={lane === BACKLOG ? 'No issues in the backlog.' : 'Nothing planned.'}
                                    hint={lane === BACKLOG ? 'Create your first issue · C' : 'Drag an issue here.'}
                                />
                            </li>
                        )}
                        {cards.map((card) => (
                            <SortableRow key={card.key} card={card} columns={columns} disabled={!draggable} />
                        ))}
                    </ul>
                </SortableContext>
            )}
        </section>
    );
}

function SortableRow({ card, columns, disabled }: { card: Card; columns: string[]; disabled: boolean }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: card.key, disabled, transition: css('drop') });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={`rounded-ctl ${isDragging ? 'opacity-40' : ''}`}
            {...attributes}
            {...listeners}
        >
            <Row card={card} columns={columns} />
        </li>
    );
}

function Row({ card, columns, dragging = false }: { card: Card; columns: string[]; dragging?: boolean }) {
    return (
        <div
            className={`flex h-11 items-center gap-2.5 rounded-ctl border px-3 ${dragging ? 'border-line-2 bg-card shadow-lift' : 'border-transparent bg-card-2 hover:border-line-2'}`}
        >
            <IssueTypeIcon type={card.type} subtask={!!card.parent} />
            <Link href={issueUrl(card.key)} className="shrink-0 text-2xs text-muted hover:underline">
                {card.key}
            </Link>
            <span className="min-w-0 flex-1 truncate text-md" title={card.title}>
                {card.title}
            </span>
            <span className="hidden max-w-32 sm:block">
                <StatusLozenge status={card.status} columns={columns} />
            </span>
            {card.story_points !== null && <span className="shrink-0 text-sm text-muted">{card.story_points} pts</span>}
            <PriorityIcon priority={card.priority} />
            {card.assignee ? (
                <Avatar name={card.assignee.name} email={card.assignee.email} />
            ) : (
                <span className="h-6 w-6 shrink-0 rounded-pill border border-dashed border-line-2" title="Unassigned" />
            )}
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
                <h2 className="text-heading-2xl text-text">{existing ? `Edit ${existing.name}` : 'New sprint'}</h2>
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
