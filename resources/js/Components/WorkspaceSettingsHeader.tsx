import { Link } from '@inertiajs/react';

const tabs = [
    ['settings.general', 'General'],
    ['settings.members', 'Members'],
    ['settings.audit-log', 'Audit log'],
] as const;

/** Title and tabs shared by the workspace settings pages (spec 7.0b). */
export default function WorkspaceSettingsHeader() {
    return (
        <div>
            <h1 className="text-display text-text">Workspace settings</h1>
            <nav aria-label="Workspace settings" className="mt-3 flex gap-1 border-b border-line">
                {tabs.map(([name, label]) => {
                    const current = route().current(name);
                    return (
                        <Link
                            key={name}
                            href={route(name)}
                            aria-current={current ? 'page' : undefined}
                            className={`-mb-px flex h-control-h items-center border-b-2 px-3 text-md ${current ? 'border-primary text-text' : 'border-transparent text-muted hover:text-text'}`}
                        >
                            {label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
