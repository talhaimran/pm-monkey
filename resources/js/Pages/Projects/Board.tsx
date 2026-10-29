import Avatar from '@/Components/Avatar';
import IssueFilters, { Filters } from '@/Components/IssueFilters';
import IssueFormModal from '@/Components/IssueFormModal';
import IssueTypeIcon from '@/Components/IssueTypeIcon';
import PriorityIcon from '@/Components/PriorityIcon';
import PrimaryButton from '@/Components/PrimaryButton';
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
    filters,
    options,
    can,
}: {
    project: Project;
    board: BoardData;
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
    const openCreate = useCallback(() => setCreating(true), []);
    useCreateShortcut(openCreate, can.contribute);
    const columnNames = board.columns.map((c) => c.name);

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
                <ProjectHeader project={project} title={`${project.key} board`}>
                    {can.contribute && <PrimaryButton onClick={openCreate}>Create issue</PrimaryButton>}
                </ProjectHeader>
            }
        >
            <Head title={`${project.key} board`} />

            <div className="px-4 py-4 sm:px-6 lg:px-8">
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
                    <p className="mb-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                        Showing the first 500 issues. Use filters to narrow the board.
                    </p>
                )}

                <DndContext sensors={sensors} collisionDetection={closestCorners} {...handlers}>
                    <div className="flex gap-4 overflow-x-auto pb-4">
                        {columnNames.map((name) => (
                            <Lane key={name} name={name} cards={lanes[name] ?? []} draggable={can.contribute} />
                        ))}
                    </div>
                    <DragOverlay>{active ? <CardView card={active} dragging /> : null}</DragOverlay>
                </DndContext>
            </div>

            {can.contribute && (
                <IssueFormModal show={creating} onClose={() => setCreating(false)} projectKey={project.key} options={options} />
            )}
        </AuthenticatedLayout>
    );
}

function Lane({ name, cards, draggable }: { name: string; cards: Card[]; draggable: boolean }) {
    const { setNodeRef, isOver } = useLaneDroppable(name);

    return (
        <section
            ref={setNodeRef}
            aria-label={`${name} column`}
            className={`flex w-72 shrink-0 flex-col rounded-lg bg-gray-100 p-2 dark:bg-gray-800/60 ${isOver ? 'ring-2 ring-indigo-400' : ''}`}
        >
            <h3 className="mb-2 flex items-center justify-between px-1 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                {name}
                <span className="rounded-full bg-gray-200 px-2 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{cards.length}</span>
            </h3>
            <SortableContext items={cards.map((c) => c.key)} strategy={verticalListSortingStrategy}>
                <ul className="flex min-h-16 flex-col gap-2">
                    {cards.map((card) => (
                        <SortableCard key={card.key} card={card} disabled={!draggable} />
                    ))}
                </ul>
            </SortableContext>
        </section>
    );
}

function SortableCard({ card, disabled }: { card: Card; disabled: boolean }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: card.key, disabled });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={isDragging ? 'opacity-40' : ''}
            {...attributes}
            {...listeners}
        >
            <CardView card={card} />
        </li>
    );
}

function CardView({ card, dragging = false }: { card: Card; dragging?: boolean }) {
    return (
        <div
            className={`rounded-md bg-white p-3 text-sm shadow-sm dark:bg-gray-900 ${dragging ? 'rotate-2 shadow-lg ring-2 ring-indigo-400' : ''}`}
        >
            <Link href={route('issues.show', card.key)} className="block font-medium text-gray-900 hover:underline dark:text-gray-100">
                {card.title}
            </Link>
            {card.labels.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {card.labels.map((l) => (
                        <span key={l} className="rounded bg-gray-100 px-1.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {l}
                        </span>
                    ))}
                </div>
            )}
            <div className="mt-2 flex items-center gap-1.5 text-xs text-ink-subtle">
                <IssueTypeIcon type={card.type} subtask={!!card.parent} />
                <span className="font-semibold">{card.key}</span>
                <span className="ml-auto flex items-center gap-1.5">
                    {card.story_points !== null && (
                        <span className="rounded-full bg-neutral px-1.5 font-semibold text-ink" title="Story points">
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
