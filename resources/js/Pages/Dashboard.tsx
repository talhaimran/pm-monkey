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
        <AuthenticatedLayout header={<h1 className="text-display text-text">Your work</h1>}>
            <Head title="Dashboard" />

            <div className="space-y-8 px-4 pb-10 md:px-8">
                <section>
                    <h3 className="mb-3 text-body font-semibold text-text">My issues</h3>
                    {myIssues.length === 0 ? (
                        <p className="rounded-card bg-bg p-6 text-sm text-muted shadow-xs">
                            Nothing open is assigned to you.
                        </p>
                    ) : (
                        <IssueTable issues={myIssues} />
                    )}
                </section>

                <section>
                    <h3 className="mb-3 text-body font-semibold text-text">Projects</h3>
                    {projects.length === 0 && (
                        <p className="text-sm text-muted">
                            No projects yet. <Link href={route('projects.index')} className="text-primary underline">Create one</Link>.
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
        <article className="rounded-ctl border border-line bg-bg p-4">
            <header className="flex items-baseline justify-between gap-2">
                <Link href={route('projects.show', project.key)} className="font-semibold text-text hover:underline">
                    <span className="font-mono text-primary">{project.key}</span> {project.name}
                </Link>
                <span className="text-xs text-muted">
                    {summary.open} open{summary.overdue > 0 && <span className="text-t-bad"> · {summary.overdue} overdue</span>}
                </span>
            </header>

            <dl className="mt-3 space-y-1">
                {summary.statuses.map((s) => (
                    <div key={s.name} className="flex items-center gap-2 text-xs">
                        <dt className="w-24 truncate text-muted">{s.name}</dt>
                        <dd className="flex flex-1 items-center gap-2">
                            <span className="h-2 rounded-ctl bg-primary" style={{ width:`${(s.count / max) * 100}%`, minWidth: s.count ? '4px' : 0 }} />
                            <span className="text-text">{s.count}</span>
                        </dd>
                    </div>
                ))}
            </dl>

            <div className="mt-4 border-t border-line pt-3 text-xs">
                {sprint ? (
                    <>
                        <div className="flex justify-between text-text">
                            <span className="font-medium">{sprint.name}</span>
                            <span>
                                {sprint.points_done}/{sprint.points_total} pts · {sprint.issues_done}/{sprint.issue_count} issues
                            </span>
                        </div>
                        <div
                            className="mt-1 h-2 overflow-hidden rounded-ctl bg-card-2"
                            role="progressbar"
                            aria-label={`${sprint.name} points done`}
                            aria-valuenow={pct}
                            aria-valuemin={0}
                            aria-valuemax={100}
                        >
                            <div className="h-full bg-c-good" style={{ width:`${pct}%` }} />
                        </div>
                        {sprint.end_date && <p className="mt-1 text-muted">Ends {sprint.end_date}</p>}
                    </>
                ) : (
                    <p className="text-muted">No active sprint.</p>
                )}
            </div>
        </article>
    );
}
