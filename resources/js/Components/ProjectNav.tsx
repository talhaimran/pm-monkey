import { Columns3, List, ListTodo, Settings } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

/** The project's sections, shown in the sidebar under the project's name: route name, label, icon. */
export const projectLinks = [
    ['projects.show', 'Board', Columns3],
    ['projects.backlog', 'Backlog', ListTodo],
    ['projects.issues.index', 'Issues', List],
    ['projects.settings', 'Project settings', Settings],
] as const;

/** Square project avatar: the key's first letter on brand colour. */
export function ProjectAvatar({ projectKey, className = 'h-6 w-6 text-xs' }: { projectKey: string; className?: string }) {
    return (
        <span aria-hidden="true" className={`flex shrink-0 items-center justify-center rounded-sm bg-primary text-on-primary ${className}`}>
            {projectKey[0]}
        </span>
    );
}

/** Breadcrumbs (Projects / name / …), a large title and right-aligned actions, used at the top of project pages. */
export function ProjectHeader({
    project,
    title,
    crumbs = [],
    children,
}: {
    project: { key: string; name: string };
    title?: ReactNode;
    crumbs?: { label: string; href: string }[];
    children?: ReactNode;
}) {
    const trail = [
        { label: 'Projects', href: route('projects.index') },
        { label: project.name, href: route('projects.show', project.key) },
        ...crumbs,
    ];

    return (
        <div>
            <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 text-sm text-muted">
                {trail.map((c, i) => (
                    <span key={c.href} className="flex items-center gap-1">
                        {i > 0 && <span aria-hidden="true">/</span>}
                        <Link href={c.href} className="hover:text-text hover:underline">
                            {c.label}
                        </Link>
                    </span>
                ))}
            </nav>
            {title !== undefined && (
                <div className="mt-2 flex flex-wrap items-start justify-between gap-3">
                    <h1 className="min-w-0 break-words text-display text-text">{title}</h1>
                    {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
                </div>
            )}
        </div>
    );
}
