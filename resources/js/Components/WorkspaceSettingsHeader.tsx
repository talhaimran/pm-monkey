import { Link, usePage } from '@inertiajs/react';

// [route, label, Owners and Admins only]
const tabs = [
    ['settings.general', 'General', true],
    ['settings.members', 'Members', true],
    ['settings.security', 'Security', true],
    ['settings.audit-log', 'Audit log', true],
    ['settings.usage', 'Usage', false],
] as const;

/** Title and tabs shared by the workspace settings pages (spec 7.0b). */
export default function WorkspaceSettingsHeader() {
    const role = usePage().props.workspace?.role;
    const admin = role === 'owner' || role === 'admin';
    return (
        <div>
            <h1 className="text-display text-text">Workspace settings</h1>
            <nav aria-label="Workspace settings" className="mt-3 flex gap-1 overflow-x-auto border-b border-line">
                {tabs.filter(([, , adminOnly]) => !adminOnly || admin).map(([name, label]) => {
                    const current = route().current(name);
                    return (
                        <Link
                            key={name}
                            href={route(name)}
                            aria-current={current ? 'page' : undefined}
                            className={`-mb-px flex h-control-h shrink-0 items-center whitespace-nowrap border-b-2 px-3 text-md ${current ? 'border-primary text-text' : 'border-transparent text-muted hover:text-text'}`}
                        >
                            {label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
