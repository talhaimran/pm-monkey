import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';

/** The workspace's initial in a tinted square (D4: no logo upload until step 6). */
function Mark({ name, tone }: { name: string; tone: string }) {
    return (
        <span aria-hidden="true" className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-tile text-sm ${tone}`}>
            {name.trim().charAt(0).toUpperCase()}
        </span>
    );
}

const item = 'flex h-control-h w-full items-center gap-2.5 rounded-ctl px-2.5 text-start text-md text-text data-[focus]:bg-card-2';

/** Design system SidebarNav: the switcher at the top of the sidebar. */
export default function WorkspaceSwitcher() {
    const { workspace, workspaces, edition } = usePage().props;
    if (!workspace) return null;

    return (
        <Menu as="div" className="relative mb-2 max-md:me-9">
            <MenuButton
                aria-label={`Workspace: ${workspace.name}. Switch workspace`}
                className="flex h-[42px] w-full items-center gap-2.5 rounded-ctl px-2 text-start text-on-side hover:bg-side-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-side-accent"
            >
                <Mark name={workspace.name} tone="bg-side-pill text-side-accent" />
                <span className="min-w-0 flex-1 truncate text-lg">{workspace.name}</span>
                <ChevronsUpDown className="h-4 w-4 shrink-0 text-side-dim" aria-hidden="true" />
            </MenuButton>

            <MenuItems anchor="bottom start" className="z-50 mt-1 w-[--button-width] min-w-56 rounded-card bg-card p-1.5 shadow-pop">
                <div className="px-2.5 pb-1 pt-1.5 text-2xs uppercase text-muted">Workspaces</div>
                {workspaces.map((w) => (
                    <MenuItem key={w.slug}>
                        <Link href={route('dashboard', { workspace: w.slug })} className={item} aria-current={w.slug === workspace.slug ? 'true' : undefined}>
                            <Mark name={w.name} tone="bg-card-2 text-text" />
                            <span className="min-w-0 flex-1 truncate">{w.name}</span>
                            {w.slug === workspace.slug && <Check className="h-4 w-4 text-primary" aria-label="Current" />}
                        </Link>
                    </MenuItem>
                ))}
                {/* "Workspace settings" for Owners and Admins arrives with the settings pages (step 0 PR 3). */}
                {edition === 'cloud' && <div className="my-1 border-t border-line" />}
                {edition === 'cloud' && (
                    <MenuItem>
                        <Link href={route('workspaces.create')} className={item}>
                            <Plus className="h-4 w-4 text-muted" aria-hidden="true" />
                            Create workspace
                        </Link>
                    </MenuItem>
                )}
            </MenuItems>
        </Menu>
    );
}
