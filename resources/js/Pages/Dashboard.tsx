import IssueTable from '@/Components/IssueTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Issue, ProjectRole } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Summary {
    statuses: { name: string; count: number }[];
    types: Record<string, number>;
    total: number;
    open: number;
    overdue: number;
    active_sprint: {
        name: string;
        start_date: string | null;
        end_date: string | null;
        issue_count: number;
        issues_done: number;
        points_total: number;
        points_done: number;
    } | null;
}

export default function Dashboard({
    myIssues,
    projects,
}: {
    myIssues: Issue[];
    projects: { key: string; name: string; my_role: ProjectRole; summary: Summary }[];
}) {
    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold text-ink">Your work</h1>}>
            <Head title="Dashboard" />

            <div className="space-y-8 px-4 pb-10 sm:px-6">
                <section>
                    <h3 className="mb-3 text-base font-semibold text-ink">My issues</h3>
                    {myIssues.length === 0 ? (
                        <p className="rounded-lg bg-surface p-6 text-sm text-ink-subtle shadow-sm">
                            Nothing open is assigned to you.
                        </p>
                    ) : (
                        <IssueTable issues={myIssues} />
                    )}
                </section>

                <section>
                    <h3 className="mb-3 text-base font-semibold text-ink">Projects</h3>
                    {projects.length === 0 && (
                        <p className="text-sm text-ink-subtle">
                            No projects yet. <Link href={route('projects.index')} className="text-brand underline">Create one</Link>.
                        </p>
                    )}
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((p) => (
                            <ProjectCard key={p.key} project={p} />
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function ProjectCard({ project }: { project: { key: string; name: string; summary: Summary } }) {
    const { summary } = project;
    const sprint = summary.active_sprint;
    const max = Math.max(1, ...summary.statuses.map((s) => s.count));
    const pct = sprint && sprint.points_total > 0 ? Math.round((sprint.points_done / sprint.points_total) * 100) : 0;

    return (
        <article className="rounded border border-line bg-surface p-4">
            <header className="flex items-baseline justify-between gap-2">
                <Link href={route('projects.show', project.key)} className="font-semibold text-ink hover:underline">
                    <span className="font-mono text-brand">{project.key}</span> {project.name}
                </Link>
                <span className="text-xs text-ink-subtle">
                    {summary.open} open{summary.overdue > 0 && <span className="text-danger"> · {summary.overdue} overdue</span>}
                </span>
            </header>

            <dl className="mt-3 space-y-1">
                {summary.statuses.map((s) => (
                    <div key={s.name} className="flex items-center gap-2 text-xs">
                        <dt className="w-24 truncate text-ink-subtle">{s.name}</dt>
                        <dd className="flex flex-1 items-center gap-2">
                            <span className="h-2 rounded bg-brand" style={{ width:`${(s.count / max) * 100}%`, minWidth: s.count ? '4px' : 0 }} />
                            <span className="text-ink">{s.count}</span>
                        </dd>
                    </div>
                ))}
            </dl>

            <div className="mt-4 border-t border-line pt-3 text-xs">
                {sprint ? (
                    <>
                        <div className="flex justify-between text-ink">
                            <span className="font-medium">{sprint.name}</span>
                            <span>
                                {sprint.points_done}/{sprint.points_total} pts · {sprint.issues_done}/{sprint.issue_count} issues
                            </span>
                        </div>
                        <div
                            className="mt-1 h-2 overflow-hidden rounded bg-neutral"
                            role="progressbar"
                            aria-label={`${sprint.name} points done`}
                            aria-valuenow={pct}
                            aria-valuemin={0}
                            aria-valuemax={100}
                        >
                            <div className="h-full bg-success" style={{ width:`${pct}%` }} />
                        </div>
                        {sprint.end_date && <p className="mt-1 text-ink-subtle">Ends {sprint.end_date}</p>}
                    </>
                ) : (
                    <p className="text-ink-subtle">No active sprint.</p>
                )}
            </div>
        </article>
    );
}
