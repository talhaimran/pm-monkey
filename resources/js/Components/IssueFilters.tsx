import { selectClass } from '@/Components/IssueFormModal';
import Avatar from '@/Components/Avatar';
import TextInput from '@/Components/TextInput';
import { IssueOptions } from '@/types';
import { useEffect, useState } from 'react';

export type Filters = Partial<Record<'assignee' | 'type' | 'priority' | 'label' | 'q' | 'sprint' | 'sort', string>>;

/** Search box plus assignee/type/priority/label/sprint selects. Calls onApply with changed keys; '' clears one. */
export default function IssueFilters({
    filters,
    options,
    onApply,
    onClear,
    types = options.types,
}: {
    filters: Filters;
    options: IssueOptions;
    onApply: (next: Filters) => void;
    onClear: () => void;
    types?: string[];
}) {
    const [search, setSearch] = useState(filters.q ?? '');

    useEffect(() => {
        if (search === (filters.q ?? '')) return;
        const t = setTimeout(() => onApply({ q: search }), 300);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const select = (name: keyof Filters, label: string, choices: { value: string; label: string }[]) => (
        <select
            aria-label={label}
            className={`${selectClass} w-auto !border-transparent font-medium ${filters[name] ? '!bg-selected !text-brand' : '!bg-neutral hover:!bg-neutral-hover'}`}
            value={filters[name] ?? ''}
            onChange={(e) => onApply({ [name]: e.target.value })}
        >
            <option value="">{label}</option>
            {choices.map((c) => (
                <option key={c.value} value={c.value}>
                    {c.label}
                </option>
            ))}
        </select>
    );
    const plain = (values: string[]) => values.map((v) => ({ value: v, label: v }));
    const active = Object.entries(filters).some(([k, v]) => k !== 'sort' && v);
    const toggle = (assignee: string) => onApply({ assignee: filters.assignee === assignee ? '' : assignee });
    const ring = (on: boolean) =>
        `rounded-full ring-2 transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-brand ${on ? 'z-10 ring-brand' : 'ring-surface'}`;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative">
                <svg viewBox="0 0 16 16" className="pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-subtle" aria-hidden="true">
                    <circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
                    <path d="m10.5 10.5 3 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                </svg>
                <TextInput
                    type="search"
                    aria-label="Search this board"
                    placeholder="Search"
                    className="w-40 ps-8 sm:w-44"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>
            {/* shortcut: every member gets an avatar toggle, fine for small teams; switch to "+N" overflow past ~10 members. */}
            <div className="flex items-center -space-x-1" role="group" aria-label="Filter by assignee">
                {options.members.map((m) => (
                    <button
                        key={m.email}
                        type="button"
                        aria-pressed={filters.assignee === m.email}
                        aria-label={`Assignee: ${m.name}`}
                        className={ring(filters.assignee === m.email)}
                        onClick={() => toggle(m.email)}
                    >
                        <Avatar name={m.name} email={m.email} size="md" />
                    </button>
                ))}
                <button
                    type="button"
                    aria-pressed={filters.assignee === 'unassigned'}
                    aria-label="Unassigned"
                    title="Unassigned"
                    className={`${ring(filters.assignee === 'unassigned')} flex h-8 w-8 items-center justify-center bg-neutral text-ink-subtle`}
                    onClick={() => toggle('unassigned')}
                >
                    <svg viewBox="0 0 16 16" className="h-4 w-4" aria-hidden="true">
                        <circle cx="8" cy="5.5" r="2.5" fill="currentColor" />
                        <path d="M3 13.5a5 5 0 0 1 10 0z" fill="currentColor" />
                    </svg>
                </button>
            </div>
            {select('type', 'Type', plain(types))}
            {options.labels.length > 0 && select('label', 'Label', plain(options.labels.map((l) => l.name)))}
            {select('priority', 'Priority', plain(options.priorities))}
            {options.sprints.length > 0 &&
                select('sprint', 'Sprint', [
                    { value: 'active', label: 'Active sprint' },
                    { value: 'backlog', label: 'Backlog (no sprint)' },
                    ...plain(options.sprints),
                ])}
            {active && (
                <button
                    type="button"
                    className="h-8 rounded-[3px] px-2 text-sm font-medium text-ink-subtle hover:bg-neutral hover:text-ink"
                    onClick={() => {
                        setSearch('');
                        onClear();
                    }}
                >
                    Clear filters
                </button>
            )}
        </div>
    );
}
