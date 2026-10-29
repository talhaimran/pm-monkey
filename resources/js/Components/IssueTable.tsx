import Avatar from '@/Components/Avatar';
import IssueTypeIcon from '@/Components/IssueTypeIcon';
import PriorityIcon from '@/Components/PriorityIcon';
import StatusLozenge from '@/Components/StatusLozenge';
import { Issue } from '@/types';
import { Link } from '@inertiajs/react';

type Column = { sort?: string; label: string; className?: string; cell: (i: Issue, statuses?: string[]) => React.ReactNode };

const columns: Column[] = [
    { sort: 'type', label: 'Type', className: 'w-12', cell: (i) => <IssueTypeIcon type={i.type} subtask={!!i.parent} /> },
    {
        sort: 'key',
        label: 'Key',
        className: 'w-24 whitespace-nowrap',
        cell: (i) => (
            <Link href={route('issues.show', i.key)} className="text-brand hover:underline">
                {i.key}
            </Link>
        ),
    },
    {
        sort: 'title',
        label: 'Summary',
        className: 'min-w-48',
        cell: (i) => (
            <Link href={route('issues.show', i.key)} className="line-clamp-1 hover:text-brand hover:underline">
                {i.title}
            </Link>
        ),
    },
    { sort: 'status', label: 'Status', className: 'w-36', cell: (i, statuses) => <StatusLozenge status={i.status} columns={statuses} /> },
    {
        sort: 'assignee',
        label: 'Assignee',
        className: 'hidden md:table-cell w-44',
        cell: (i) =>
            i.assignee ? (
                <span className="flex items-center gap-2">
                    <Avatar name={i.assignee.name} email={i.assignee.email} />
                    <span className="truncate">{i.assignee.name}</span>
                </span>
            ) : (
                <span className="text-ink-subtle">Unassigned</span>
            ),
    },
    {
        sort: 'priority',
        label: 'Priority',
        className: 'hidden md:table-cell w-28',
        cell: (i) => (
            <span className="flex items-center gap-1.5 capitalize">
                <PriorityIcon priority={i.priority} />
                {i.priority}
            </span>
        ),
    },
    { sort: 'story_points', label: 'Points', className: 'hidden lg:table-cell w-16 text-right', cell: (i) => i.story_points ?? '' },
    {
        sort: 'updated',
        label: 'Updated',
        className: 'hidden xl:table-cell w-28 whitespace-nowrap',
        cell: (i) => new Date(i.updated_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }),
    },
];

/**
 * Jira-style compact issue list with clickable sortable headers. `sort` is the current value ("-" prefix = descending).
 * Pass the project's `statuses` (column names) to colour status lozenges.
 * shortcut: cross-project search passes none, so its lozenges are gray; add a status category to IssueResource if that matters.
 */
export default function IssueTable({ issues, sort, onSort, statuses }: { issues: Issue[]; sort?: string; onSort?: (sort: string) => void; statuses?: string[] }) {
    const current = sort ?? '-key';

    return (
        <div className="overflow-x-auto border-t border-line">
            <table className="min-w-full text-left">
                <thead className="border-b-2 border-line bg-surface text-xs font-semibold text-ink-subtle">
                    <tr>
                        {columns.map((c) => {
                            const active = current.replace('-', '') === c.sort;
                            const desc = current.startsWith('-');
                            return (
                                <th
                                    key={c.label}
                                    scope="col"
                                    className={`px-2 py-2 font-semibold ${c.className ?? ''}`}
                                    aria-sort={active ? (desc ? 'descending' : 'ascending') : 'none'}
                                >
                                    {onSort && c.sort ? (
                                        <button
                                            className={`-mx-1 rounded-[3px] px-1 hover:bg-neutral hover:text-ink ${active ? 'text-ink' : ''}`}
                                            onClick={() => onSort(active && !desc ? `-${c.sort}` : c.sort!)}
                                        >
                                            {c.label} {active ? (desc ? '↓' : '↑') : ''}
                                        </button>
                                    ) : (
                                        c.label
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {issues.length === 0 && (
                        <tr>
                            <td colSpan={columns.length} className="px-3 py-10 text-center text-ink-subtle">
                                No issues match.
                            </td>
                        </tr>
                    )}
                    {issues.map((i) => (
                        <tr key={i.id} className="h-10 hover:bg-sunken">
                            {columns.map((c) => (
                                <td key={c.label} className={`px-2 py-1.5 ${c.className ?? ''}`}>
                                    {c.cell(i, statuses)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
