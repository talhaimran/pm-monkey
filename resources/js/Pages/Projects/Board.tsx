import Avatar from '@/Components/Avatar';
import CompleteSprintModal from '@/Components/CompleteSprintModal';
import IssueFilters, { Filters } from '@/Components/IssueFilters';
import IssueFormModal from '@/Components/IssueFormModal';
import IssueTypeIcon from '@/Components/IssueTypeIcon';
import PriorityIcon from '@/Components/PriorityIcon';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { ProjectHeader } from '@/Components/ProjectNav';
import { useCreateShortcut } from '@/Components/useCreateShortcut';
import { placement, useLaneDroppable, useSortableLanes } from '@/Components/useSortableLanes';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { IssueOptions, Project } from '@/types';
import { closestCorners, DndContext, DragOverlay } from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

export interface Card {
    key: string;
    title: string;
    type: string;
    priority: string;
    status: string;
    assignee: { name: string; email: string } | null;
    labels: string[];
    story_points: number | null;
    due_date: string | null;
    parent: string | null;
}

interface BoardData {
    columns: { name: string; position: number; issues: Card[] }[];
    truncated: boolean;
}


const toLanes = (board: BoardData) =>
    Object.fromEntries(board.columns.map((c) => [c.name, c.issues])) as Record<string, Card[]>;

export default function Board({
    project,
    board,
    active_sprint: sprint,
    filters,
    options,
    can,
}: {
    project: Project;
    board: BoardData;
    active_sprint: { id: number; name: string; goal: string | null; start_date: string | null; end_date: string | null } | null;
    filters: Filters;
    options: IssueOptions;
    can: { contribute: boolean };
}) {
    const source = useMemo(() => toLanes(board), [board]);
    const { lanes, active, sensors, reset, handlers } = useSortableLanes(source, (key, lane, laneChanged, index, cards) =>
        router.post(
            route('issues.move', key),
            { ...(laneChanged ? { status: lane } : {}), ...placement(index, cards) },
            { preserveScroll: true, preserveState: true, onError: reset },
        ),
    );
    const [creating, setCreating] = useState(false);
    const [completing, setCompleting] = useState(false);
    const openCreate = useCallback(() => setCreating(true), []);
    useCreateShortcut(openCreate, can.contribute);
    const columnNames = board.columns.map((c) => c.name);
    const sprintOnly = !!sprint && (filters.sprint === 'active' || filters.sprint === sprint.name);

    const applyFilters = (next: Filters) =>
        router.get(
            route('projects.show', project.key),
            Object.fromEntries(Object.entries({ ...filters, ...next }).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AuthenticatedLayout
            project={project}
            header={
                // The board shows every issue unless filtered, so the sprint is the title only when the board is scoped to it.
                <ProjectHeader project={project} title={sprintOnly ? sprint!.name : `${project.key} board`}>
                    {sprint && (
                        <span className="text-ink-subtle" title={sprint.end_date ? `${sprint.start_date} to ${sprint.end_date}` : undefined}>
                            {!sprintOnly && (
                                <Link href={route('projects.show', [project.key, { sprint: 'active' }])} className="font-medium text-brand hover:underline">
                                    {sprint.name}
                                </Link>
                            )}
                            {!sprintOnly && sprint.end_date && ' · '}
                            {sprint.end_date && daysLeft(sprint.end_date)}
                        </span>
                    )}
                    {sprint && can.contribute && <SecondaryButton onClick={() => setCompleting(true)}>Complete sprint</SecondaryButton>}
                    {can.contribute && <PrimaryButton onClick={openCreate}>Create issue</PrimaryButton>}
                </ProjectHeader>
            }
        >
            <Head title={`${project.key} board`} />

            <div className="px-4 pb-4 sm:px-6">
                {sprintOnly && sprint?.goal && <p className="-mt-1 mb-3 text-ink-subtle">{sprint.goal}</p>}
                <div className="mb-4">
                    <IssueFilters
                        filters={filters}
                        options={options}
                        types={options.types.filter((t) => t !== 'epic')}
                        onApply={applyFilters}
                        onClear={() => router.get(route('projects.show', project.key), {}, { preserveState: true, replace: true })}
                    />
                </div>

                {board.truncated && (
                    <p className="mb-3 rounded-[3px] bg-loz-progress px-3 py-2 text-loz-progress-ink">
                        Showing the first 500 issues. Use filters to narrow the board.
                    </p>
                )}

                <DndContext sensors={sensors} collisionDetection={closestCorners} {...handlers}>
                    <div className="flex items-start gap-2 overflow-x-auto pb-4">
                        {columnNames.map((name) => (
                            <Lane key={name} name={name} done={name === columnNames.at(-1)} cards={lanes[name] ?? []} draggable={can.contribute} />
                        ))}
                    </div>
                    <DragOverlay>{active ? <CardView card={active} dragging /> : null}</DragOverlay>
                </DndContext>
            </div>

            {sprint && (
                <CompleteSprintModal
                    project={project}
                    sprint={completing ? sprint : null}
                    planned={options.sprints.filter((n) => n !== sprint.name)}
                    onClose={() => setCompleting(false)}
                />
            )}
            {can.contribute && (
                <IssueFormModal show={creating} onClose={() => setCreating(false)} projectKey={project.key} options={options} />
            )}
        </AuthenticatedLayout>
    );
}

const daysLeft = (end: string) => {
    const days = Math.ceil((new Date(`${end}T23:59:59`).getTime() - Date.now()) / 86_400_000);
    return days < 0 ? 'Sprint ended' : `${days} day${days === 1 ? '' : 's'} left`;
};

function Lane({ name, cards, done, draggable }: { name: string; cards: Card[]; done: boolean; draggable: boolean }) {
    const { setNodeRef, isOver } = useLaneDroppable(name);

    return (
        <section
            ref={setNodeRef}
            aria-label={`${name} column`}
            className={`flex min-h-[calc(100vh-16rem)] w-[272px] shrink-0 flex-col rounded-md bg-sunken p-1.5 ${isOver ? 'outline outline-2 outline-brand' : ''}`}
        >
            <h3 className="flex items-center gap-2 px-2 pb-2 pt-1.5 text-xs font-semibold uppercase text-ink-subtle">
                <span className="truncate">{name}</span>
                <span>{cards.length}</span>
                {done && (
                    <svg viewBox="0 0 16 16" className="h-3.5 w-3.5 text-success" aria-label="Done column">
                        <path d="m3.5 8.5 3 3 6-7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                )}
            </h3>
            <SortableContext items={cards.map((c) => c.key)} strategy={verticalListSortingStrategy}>
                <ul className="flex min-h-16 flex-col gap-1">
                    {cards.map((card) => (
                        <SortableCard key={card.key} card={card} done={done} disabled={!draggable} />
                    ))}
                </ul>
            </SortableContext>
        </section>
    );
}

function SortableCard({ card, done, disabled }: { card: Card; done: boolean; disabled: boolean }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: card.key, disabled });

    return (
        <li ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} {...attributes} {...listeners}>
            {/* Jira leaves a sunken placeholder where the dragged card came from. */}
            {isDragging ? <div className="h-20 rounded border-2 border-dashed border-line bg-sunken" /> : <CardView card={card} done={done} />}
        </li>
    );
}

function CardView({ card, done = false, dragging = false }: { card: Card; done?: boolean; dragging?: boolean }) {
    return (
        <div
            className={`rounded bg-raised p-3 text-ink shadow-card dark:shadow-none dark:ring-1 dark:ring-line hover:bg-sunken ${
                dragging ? 'rotate-[3deg] shadow-xl outline outline-2 outline-brand' : ''
            }`}
        >
            <Link href={route('issues.show', card.key)} className="block break-words hover:underline">
                {card.title}
            </Link>
            {card.labels.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {card.labels.map((l) => (
                        <span key={l} className="max-w-full truncate rounded-[3px] border border-line px-1 text-xs text-ink-subtle">
                            {l}
                        </span>
                    ))}
                </div>
            )}
            <div className="mt-2 flex items-center gap-1.5 text-xs text-ink-subtle">
                <IssueTypeIcon type={card.type} subtask={!!card.parent} />
                <span className={`font-semibold ${done ? 'line-through' : ''}`} title={card.parent ? `Sub-task of ${card.parent}` : undefined}>
                    {card.key}
                </span>
                <span className="ml-auto flex items-center gap-1.5">
                    {card.story_points !== null && (
                        <span className="min-w-5 rounded-full bg-neutral px-1.5 text-center font-semibold text-ink" title="Story points">
                            {card.story_points}
                        </span>
                    )}
                    <PriorityIcon priority={card.priority} />
                    {card.assignee && <Avatar name={card.assignee.name} email={card.assignee.email} />}
                </span>
            </div>
        </div>
    );
}
