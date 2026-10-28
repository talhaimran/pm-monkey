import { selectClass } from '@/Components/IssueFormModal';
import SecondaryButton from '@/Components/SecondaryButton';
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
        <select aria-label={label} className={selectClass} value={filters[name] ?? ''} onChange={(e) => onApply({ [name]: e.target.value })}>
            <option value="">{label}: all</option>
            {choices.map((c) => (
                <option key={c.value} value={c.value}>
                    {c.label}
                </option>
            ))}
        </select>
    );
    const plain = (values: string[]) => values.map((v) => ({ value: v, label: v }));
    const active = Object.entries(filters).some(([k, v]) => k !== 'sort' && v);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <TextInput
                type="search"
                aria-label="Filter by title or key"
                placeholder="Filter by title or key…"
                className="w-full sm:w-56"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
            />
            {select('assignee', 'Assignee', [
                { value: 'unassigned', label: 'Unassigned' },
                ...options.members.map((m) => ({ value: m.email, label: m.name })),
            ])}
            {select('type', 'Type', plain(types))}
            {select('priority', 'Priority', plain(options.priorities))}
            {options.labels.length > 0 && select('label', 'Label', plain(options.labels.map((l) => l.name)))}
            {options.sprints.length > 0 &&
                select('sprint', 'Sprint', [
                    { value: 'active', label: 'Active sprint' },
                    { value: 'backlog', label: 'Backlog (no sprint)' },
                    ...plain(options.sprints),
                ])}
            {active && (
                <SecondaryButton
                    onClick={() => {
                        setSearch('');
                        onClear();
                    }}
                >
                    Clear
                </SecondaryButton>
            )}
        </div>
    );
}
