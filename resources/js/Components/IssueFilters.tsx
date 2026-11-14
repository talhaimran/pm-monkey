import { Search, UserRound } from 'lucide-react';
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
            className={`${selectClass} !h-control-h w-auto ${filters[name] ? '!border-primary !text-primary' : ''}`}
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
        `rounded-pill ring-2 ${on ? 'z-10 ring-primary' : 'ring-bg'}`;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative">
                <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                <TextInput
                    type="search"
                    aria-label="Search this board"
                    placeholder="Search"
                    className="h-control-h w-40 ps-9 sm:w-48"
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
                        <Avatar name={m.name} email={m.email} size="sm" />
                    </button>
                ))}
                <button
                    type="button"
                    aria-pressed={filters.assignee === 'unassigned'}
                    aria-label="Unassigned"
                    title="Unassigned"
                    className={`${ring(filters.assignee === 'unassigned')} flex h-8 w-8 items-center justify-center bg-card text-muted`}
                    onClick={() => toggle('unassigned')}
                >
                    <UserRound className="h-4 w-4" aria-hidden="true" />
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
                    className="h-control-h rounded-ctl px-3 text-md text-muted hover:bg-card-2 hover:text-text"
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
