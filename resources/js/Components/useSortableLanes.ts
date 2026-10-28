import {
    DragEndEvent,
    DragOverEvent,
    DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { useEffect, useRef, useState } from 'react';

const LANE_PREFIX = 'lane:';

/** Droppable target for a whole lane, so empty lanes accept drops. */
export function useLaneDroppable(lane: string) {
    return useDroppable({ id: LANE_PREFIX + lane });
}

/**
 * Drag cards within and between named lanes (board columns, backlog sections).
 * `source` must be memoised by the caller; it replaces local state whenever it changes (server wins).
 * `commit` runs once per real move with the card's lane, whether the lane changed, its index and the lane's cards.
 */
export function useSortableLanes<T extends { key: string }>(
    source: Record<string, T[]>,
    commit: (key: string, lane: string, laneChanged: boolean, index: number, cards: T[]) => void,
) {
    const [lanes, setLanes] = useState(source);
    const [active, setActive] = useState<T | null>(null);
    const origin = useRef<string | null>(null);

    useEffect(() => setLanes(source), [source]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const laneOf = (id: string, current = lanes): string | undefined =>
        id.startsWith(LANE_PREFIX)
            ? id.slice(LANE_PREFIX.length)
            : Object.keys(current).find((name) => current[name].some((c) => c.key === id));

    const onDragStart = ({ active }: DragStartEvent) => {
        const lane = laneOf(String(active.id));
        origin.current = lane ?? null;
        setActive(lane ? (lanes[lane].find((c) => c.key === active.id) ?? null) : null);
    };

    // Move the card into the hovered lane so the drop preview is right.
    const onDragOver = ({ active, over }: DragOverEvent) => {
        if (!over) return;
        setLanes((prev) => {
            const from = laneOf(String(active.id), prev);
            const to = laneOf(String(over.id), prev);
            if (!from || !to || from === to) return prev;
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
        if (!over || !lane) return setLanes(source);

        let cards = lanes[lane];
        const from = cards.findIndex((c) => c.key === key);
        const overIndex = cards.findIndex((c) => c.key === over.id);
        if (overIndex !== -1 && overIndex !== from) {
            cards = arrayMove(cards, from, overIndex);
            setLanes((prev) => ({ ...prev, [lane]: cards }));
        }

        const index = cards.findIndex((c) => c.key === key);
        const laneChanged = lane !== origin.current;
        if (!laneChanged && (source[lane] ?? []).findIndex((c) => c.key === key) === index) return;

        commit(key, lane, laneChanged, index, cards);
    };

    const onDragCancel = () => {
        setActive(null);
        setLanes(source);
    };

    return { lanes, active, sensors, reset: () => setLanes(source), handlers: { onDragStart, onDragOver, onDragEnd, onDragCancel } };
}

/** Rank payload placing a card at `index`: "after" its upper neighbour, so hidden (filtered) cards don't skew it. */
export function placement<T extends { key: string }>(index: number, cards: T[]) {
    return index === 0 ? { position: 0 } : { after: cards[index - 1].key };
}
