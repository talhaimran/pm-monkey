import IssueFormModal, { selectClass } from '@/Components/IssueFormModal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { IssueOptions, Project } from '@/types';
import {
    closestCorners,
    DndContext,
    DragEndEvent,
    DragOverEvent,
    DragOverlay,
    DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export interface Card {
    key: string;
    title: string;
    type: string;
    priority: string;
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

type Filters = Partial<Record<'assignee' | 'type' | 'priority' | 'label' | 'q', string>>;

const COLUMN_PREFIX = 'column:';

const typeColors: Record<string, string> = {
    bug: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
    story: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
    task: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
};

const priorityArrows: Record<string, string> = {
    lowest: '⇊',
    low: '↓',
    medium: '=',
    high: '↑',
    highest: '⇈',
};

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
    const [lanes, setLanes] = useState(() => toLanes(board));
    const [active, setActive] = useState<Card | null>(null);
    const [creating, setCreating] = useState(false);
    const [search, setSearch] = useState(filters.q ?? '');
    const origin = useRef<string | null>(null);
    const columnNames = board.columns.map((c) => c.name);

    // Server data wins after every reload (filters, moves by others, failed moves).
    useEffect(() => setLanes(toLanes(board)), [board]);

    const applyFilters = (next: Filters) =>
        router.get(
            route('projects.show', project.key),
            Object.fromEntries(Object.entries({ ...filters, ...next }).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        if (search === (filters.q ?? '')) return;
        const t = setTimeout(() => applyFilters({ q: search }), 300);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const laneOf = (id: string): string | undefined =>
        id.startsWith(COLUMN_PREFIX)
            ? id.slice(COLUMN_PREFIX.length)
            : Object.keys(lanes).find((name) => lanes[name].some((c) => c.key === id));

    const onDragStart = ({ active }: DragStartEvent) => {
        const lane = laneOf(String(active.id));
        origin.current = lane ?? null;
        setActive(lane ? lanes[lane].find((c) => c.key === active.id) ?? null : null);
    };

    // Move the card into the hovered column so the drop preview is right.
    const onDragOver = ({ active, over }: DragOverEvent) => {
        if (!over) return;
        const from = laneOf(String(active.id));
        const to = laneOf(String(over.id));
        if (!from || !to || from === to) return;

        setLanes((prev) => {
            const card = prev[from].find((c) => c.key === active.id)!;
            const target = prev[to];
            const overIndex = target.findIndex((c) => c.key === over.id);
            const index = overIndex === -1 ? target.length : overIndex;
            return {
                ...prev,
                [from]: prev[from].filter((c) => c.key !== active.id),
                [to]: [...target.slice(0, index), card, ...target.slice(index)],
            };
        });
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        setActive(null);
        const key = String(active.id);
        const lane = laneOf(key);
        if (!over || !lane) return setLanes(toLanes(board));

        let cards = lanes[lane];
        const from = cards.findIndex((c) => c.key === key);
        const overIndex = cards.findIndex((c) => c.key === over.id);
        if (overIndex !== -1 && overIndex !== from) {
            cards = arrayMove(cards, from, overIndex);
            setLanes((prev) => ({ ...prev, [lane]: cards }));
        }

        const index = cards.findIndex((c) => c.key === key);
        const sameLane = lane === origin.current;
        const original = toLanes(board)[lane] ?? [];
        if (sameLane && original.findIndex((c) => c.key === key) === index) return;

        // "after" (not an index) so reordering stays correct while filters hide cards.
        router.post(
            route('issues.move', key),
            {
                ...(sameLane ? {} : { status: lane }),
                ...(index === 0 ? { position: 0 } : { after: cards[index - 1].key }),
            },
            { preserveScroll: true, preserveState: true, onError: () => setLanes(toLanes(board)) },
        );
    };

    const filterSelect = (name: keyof Filters, label: string, choices: { value: string; label: string }[]) => (
        <select
            aria-label={label}
            className={selectClass}
            value={filters[name] ?? ''}
            onChange={(e) => applyFilters({ [name]: e.target.value })}
        >
            <option value="">{label}: all</option>
            {choices.map((c) => (
                <option key={c.value} value={c.value}>
                    {c.label}
                </option>
            ))}
        </select>
    );
    const plain = (values: string[]) => values.map((v) => ({ value: v, label: v }));
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        <span className="font-mono text-indigo-600 dark:text-indigo-400">{project.key}</span> {project.name}
                    </h2>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('projects.settings', project.key)}
                            className="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Settings
                        </Link>
                        {can.contribute && <PrimaryButton onClick={() => setCreating(true)}>Create issue</PrimaryButton>}
                    </div>
                </div>
            }
        >
            <Head title={`${project.key} board`} />

            <div className="px-4 py-4 sm:px-6 lg:px-8">
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <TextInput
                        type="search"
                        aria-label="Search issues"
                        placeholder="Search title or key…"
                        className="w-full sm:w-56"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    {filterSelect('assignee', 'Assignee', [
                        { value: 'unassigned', label: 'Unassigned' },
                        ...options.members.map((m) => ({ value: m.email, label: m.name })),
                    ])}
                    {filterSelect('type', 'Type', plain(options.types.filter((t) => t !== 'epic')))}
                    {filterSelect('priority', 'Priority', plain(options.priorities))}
                    {options.labels.length > 0 && filterSelect('label', 'Label', plain(options.labels.map((l) => l.name)))}
                    {hasFilters && (
                        <SecondaryButton
                            onClick={() => {
                                setSearch('');
                                router.get(route('projects.show', project.key), {}, { preserveState: true, replace: true });
                            }}
                        >
                            Clear
                        </SecondaryButton>
                    )}
                </div>

                {board.truncated && (
                    <p className="mb-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                        Showing the first 500 issues. Use filters to narrow the board.
                    </p>
                )}

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCorners}
                    onDragStart={onDragStart}
                    onDragOver={onDragOver}
                    onDragEnd={onDragEnd}
                    onDragCancel={() => {
                        setActive(null);
                        setLanes(toLanes(board));
                    }}
                >
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
    const { setNodeRef, isOver } = useDroppable({ id: COLUMN_PREFIX + name });

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
            <div className="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span className={`rounded px-1.5 uppercase ${typeColors[card.type] ?? ''}`}>{card.type}</span>
                <span className="font-mono">{card.key}</span>
                {card.parent && <span title={`Sub-task of ${card.parent}`}>↳ {card.parent}</span>}
                <span className="ml-auto flex items-center gap-2">
                    <span title={`Priority: ${card.priority}`} aria-label={`Priority ${card.priority}`}>
                        {priorityArrows[card.priority]}
                    </span>
                    {card.story_points !== null && (
                        <span className="rounded-full bg-gray-200 px-1.5 dark:bg-gray-700" title="Story points">
                            {card.story_points}
                        </span>
                    )}
                    {card.assignee && (
                        <span
                            className="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                            title={card.assignee.name}
                        >
                            {card.assignee.name
                                .split(' ')
                                .map((p) => p[0])
                                .slice(0, 2)
                                .join('')}
                        </span>
                    )}
                </span>
            </div>
        </div>
    );
}
