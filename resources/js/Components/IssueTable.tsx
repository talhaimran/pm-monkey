import Avatar from '@/Components/Avatar';
import IssueTypeIcon from '@/Components/IssueTypeIcon';
import PriorityIcon from '@/Components/PriorityIcon';
import StatusLozenge from '@/Components/StatusLozenge';
import { issueUrl, onPeekClick } from '@/issueUrl';
import { Issue } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';

type Column = { sort?: string; label: string; className?: string; cell: (i: Issue, statuses?: string[], peek?: boolean) => React.ReactNode };

const columns: Column[] = [
    { sort: 'type', label: 'Type', className: 'w-12', cell: (i) => <IssueTypeIcon type={i.type} subtask={!!i.parent} /> },
    {
        sort: 'key',
        label: 'Key',
        className: 'w-24 whitespace-nowrap',
        cell: (i) => (
            <Link href={issueUrl(i.key)} className="text-primary hover:underline">
                {i.key}
            </Link>
        ),
    },
    {
        sort: 'title',
        label: 'Summary',
        className: 'min-w-48',
        // The summary peeks where the page has a drawer; the key always goes to the full page.
        cell: (i, _statuses, peek) => (
            <Link href={issueUrl(i.key)} onClick={peek ? onPeekClick(i.key) : undefined} className="line-clamp-1 hover:text-primary hover:underline">
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
                <span className="text-muted">Unassigned</span>
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
 * Compact issue list as a real table (th scope, aria-sort) with clickable sortable headers. `sort` is the current value ("-" prefix = descending).
 * Pass the project's `statuses` (column names) to colour status lozenges.
 * shortcut: cross-project search passes none, so its lozenges are gray; add a status category to IssueResource if that matters.
 */
export default function IssueTable({
    issues,
    sort,
    onSort,
    statuses,
    peek = false,
    empty = 'No issues match these filters.',
}: {
    issues: Issue[];
    sort?: string;
    onSort?: (sort: string) => void;
    statuses?: string[];
    peek?: boolean;
    empty?: string;
}) {
    const current = sort ?? '-key';

    return (
        <div className="overflow-x-auto rounded-card border border-line bg-card shadow-xs">
            <table className="min-w-full text-left">
                <thead className="border-b border-line text-xs text-muted">
                    <tr>
                        {columns.map((c) => {
                            const active = current.replace('-', '') === c.sort;
                            const desc = current.startsWith('-');
                            return (
                                <th
                                    key={c.label}
                                    scope="col"
                                    className={`px-3 py-2.5 ${c.className ?? ''}`}
                                    aria-sort={active ? (desc ? 'descending' : 'ascending') : 'none'}
                                >
                                    {onSort && c.sort ? (
                                        <button
                                            className={`-mx-1.5 inline-flex h-control-h-sm items-center gap-1 rounded-sm px-1.5 hover:bg-card-2 hover:text-text ${active ? 'text-text' : ''}`}
                                            onClick={() => onSort(active && !desc ? `-${c.sort}` : c.sort!)}
                                        >
                                            {c.label}
                                            {active && (desc ? <ArrowDown className="h-3.5 w-3.5" aria-hidden="true" /> : <ArrowUp className="h-3.5 w-3.5" aria-hidden="true" />)}
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
                            <td colSpan={columns.length} className="px-3 py-10 text-center text-muted">
                                {empty}
                            </td>
                        </tr>
                    )}
                    {issues.map((i) => (
                        <tr key={i.id} className="h-11 hover:bg-card-2">
                            {columns.map((c) => (
                                <td key={c.label} className={`px-3 py-1.5 ${c.className ?? ''}`}>
                                    {c.cell(i, statuses, peek)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
