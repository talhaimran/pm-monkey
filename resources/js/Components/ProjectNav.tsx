import { Columns3, List, ListTodo, Settings } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

const links = [
    ['projects.backlog', 'Backlog', <ListTodo className="h-4 w-4 shrink-0" aria-hidden="true" />],
    ['projects.show', 'Board', <Columns3 className="h-4 w-4 shrink-0" aria-hidden="true" />],
    ['projects.issues.index', 'Issues', <List className="h-4 w-4 shrink-0" aria-hidden="true" />],
] as const;
const settings = [
    'projects.settings',
    'Project settings',
    <Settings className="h-4 w-4 shrink-0" aria-hidden="true" />,
] as const;

/** Square project avatar: the key's first letter on brand colour. */
export function ProjectAvatar({ projectKey, className = 'h-6 w-6 text-xs' }: { projectKey: string; className?: string }) {
    return (
        <span aria-hidden="true" className={`flex shrink-0 items-center justify-center rounded bg-primary font-bold text-on-primary ${className}`}>
            {projectKey[0]}
        </span>
    );
}

/**
 * Project navigation. Default: Jira-style left sidebar (avatar + name, Backlog, Board, Issues, Project settings).
 * `compact`: a horizontal tab row for narrow screens, where the sidebar is hidden.
 */
export default function ProjectNav({ projectKey, name, compact = false }: { projectKey: string; name: string; compact?: boolean }) {
    const item = ([routeName, label, glyph]: readonly [string, string, ReactNode]) => {
        const current = route().current(routeName);
        return (
            <Link
                key={routeName}
                href={route(routeName, projectKey)}
                aria-current={current ? 'page' : undefined}
                className={
                    compact
                        ? `whitespace-nowrap border-b-2 px-2 py-2.5 text-sm font-medium ${current ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-text'}`
                        : `relative flex h-9 items-center gap-3 rounded-ctl px-3 text-sm font-medium ${
                              current
                                  ? 'bg-well text-primary before:absolute before:inset-y-2 before:start-0 before:w-0.5 before:rounded before:bg-primary'
                                  : 'text-muted hover:bg-card-2 hover:text-text'
                          }`
                }
            >
                {!compact && glyph}
                {label}
            </Link>
        );
    };

    if (compact) {
        return (
            <nav className="-mb-px flex gap-2 overflow-x-auto" aria-label="Project">
                {[...links, settings].map(item)}
            </nav>
        );
    }

    return (
        <nav className="flex flex-col gap-0.5 p-3" aria-label="Project">
            <div className="mb-4 flex items-center gap-2 px-1 py-2">
                <ProjectAvatar projectKey={projectKey} className="h-8 w-8 text-sm" />
                <div className="min-w-0">
                    <div className="truncate font-semibold text-text">{name}</div>
                    <div className="truncate text-xs text-muted">Software project</div>
                </div>
            </div>
            <div className="px-3 pb-1 text-[11px] font-bold uppercase text-muted">Planning</div>
            {links.map(item)}
            <div className="my-3 border-t border-line" />
            {item(settings)}
        </nav>
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
                    <h1 className="min-w-0 break-words text-2xl font-semibold text-text">{title}</h1>
                    {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
                </div>
            )}
        </div>
    );
}
