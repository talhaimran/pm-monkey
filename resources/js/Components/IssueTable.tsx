import { Issue } from '@/types';
import { Link } from '@inertiajs/react';

const priorityArrows: Record<string, string> = { lowest: '⇊', low: '↓', medium: '=', high: '↑', highest: '⇈' };

type Column = { sort?: string; label: string; className?: string; cell: (i: Issue) => React.ReactNode };

const columns: Column[] = [
    { sort: 'key', label: 'Key', className: 'w-24', cell: (i) => <Link href={route('issues.show', i.key)} className="font-mono text-indigo-600 hover:underline dark:text-indigo-400">{i.key}</Link> },
    { sort: 'title', label: 'Title', cell: (i) => <Link href={route('issues.show', i.key)} className="text-gray-900 hover:underline dark:text-gray-100">{i.title}</Link> },
    { sort: 'type', label: 'Type', className: 'hidden sm:table-cell', cell: (i) => <span className="text-xs uppercase">{i.type}</span> },
    { sort: 'priority', label: 'Priority', className: 'hidden md:table-cell', cell: (i) => <span title={i.priority}>{priorityArrows[i.priority]} {i.priority}</span> },
    { sort: 'status', label: 'Status', cell: (i) => <span className="rounded bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700">{i.status}</span> },
    { sort: 'assignee', label: 'Assignee', className: 'hidden md:table-cell', cell: (i) => i.assignee?.name ?? <span className="text-gray-400">Unassigned</span> },
    { sort: 'story_points', label: 'Pts', className: 'hidden lg:table-cell text-right', cell: (i) => i.story_points ?? '–' },
    { sort: 'due_date', label: 'Due', className: 'hidden lg:table-cell', cell: (i) => i.due_date ?? '–' },
    { sort: 'updated', label: 'Updated', className: 'hidden xl:table-cell', cell: (i) => new Date(i.updated_at).toLocaleDateString() },
];

/** Issue table with clickable sortable headers. `sort` is the current value ("-" prefix = descending). */
export default function IssueTable({ issues, sort, onSort }: { issues: Issue[]; sort?: string; onSort?: (sort: string) => void }) {
    const current = sort ?? '-key';

    return (
        <div className="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-gray-800">
            <table className="min-w-full text-left text-sm text-gray-700 dark:text-gray-300">
                <thead className="border-b border-gray-100 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        {columns.map((c) => {
                            const active = current.replace('-', '') === c.sort;
                            const desc = current.startsWith('-');
                            return (
                                <th
                                    key={c.label}
                                    scope="col"
                                    className={`px-3 py-2 font-medium ${c.className ?? ''}`}
                                    aria-sort={active ? (desc ? 'descending' : 'ascending') : 'none'}
                                >
                                    {onSort && c.sort ? (
                                        <button className="uppercase hover:text-gray-900 dark:hover:text-gray-100" onClick={() => onSort(active && !desc ? `-${c.sort}` : c.sort!)}>
                                            {c.label} {active ? (desc ? '▼' : '▲') : ''}
                                        </button>
                                    ) : (
                                        c.label
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {issues.length === 0 && (
                        <tr>
                            <td colSpan={columns.length} className="px-3 py-6 text-center text-gray-500">
                                No issues match.
                            </td>
                        </tr>
                    )}
                    {issues.map((i) => (
                        <tr key={i.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            {columns.map((c) => (
                                <td key={c.label} className={`px-3 py-2 ${c.className ?? ''}`}>
                                    {c.cell(i)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
