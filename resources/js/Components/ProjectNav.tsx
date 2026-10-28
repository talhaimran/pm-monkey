import { Link } from '@inertiajs/react';

const tabs = [
    ['projects.show', 'Board'],
    ['projects.backlog', 'Backlog'],
    ['projects.issues.index', 'Issues'],
    ['projects.settings', 'Settings'],
] as const;

/** Project title plus Board / Backlog / Issues / Settings tabs, used in project page headers. */
export default function ProjectNav({ projectKey, name, children }: { projectKey: string; name: string; children?: React.ReactNode }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    <span className="font-mono text-indigo-600 dark:text-indigo-400">{projectKey}</span> {name}
                </h2>
                <nav className="flex gap-4 text-sm" aria-label="Project">
                    {tabs.map(([routeName, label]) => {
                        const current = route().current(routeName);
                        return (
                            <Link
                                key={routeName}
                                href={route(routeName, projectKey)}
                                aria-current={current ? 'page' : undefined}
                                className={
                                    current
                                        ? 'font-medium text-gray-900 underline decoration-indigo-500 decoration-2 underline-offset-8 dark:text-gray-100'
                                        : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
                                }
                            >
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            </div>
            {children && <div className="flex items-center gap-3">{children}</div>}
        </div>
    );
}
