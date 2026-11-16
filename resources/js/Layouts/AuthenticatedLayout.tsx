import ApplicationLogo from '@/Components/ApplicationLogo';
import Avatar from '@/Components/Avatar';
import { projectLinks } from '@/Components/ProjectNav';
import ThemeToggle from '@/Components/ThemeToggle';
import Toaster from '@/Components/Toaster';
import WorkspaceSwitcher from '@/Components/WorkspaceSwitcher';
import { requestCreate } from '@/Components/useCreateShortcut';
import { transition } from '@/motion';
import { isTyping } from '@/undo';
import { Dialog, DialogPanel } from '@headlessui/react';
import { Link, router, usePage } from '@inertiajs/react';
import { FolderKanban, KeyRound, LayoutDashboard, LogOut, LucideIcon, Menu, Plus, Search, UserRound, X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { FormEvent, PropsWithChildren, ReactNode, useEffect, useState } from 'react';

// Sidebar rows (design system SidebarNav): 42px, icon + label; the current page gets the pill.
const row = 'flex h-[42px] w-full items-center gap-3 rounded-ctl px-3 text-md transition-colors duration-instant';
const rest = 'text-side-dim hover:bg-side-hover hover:text-on-side';
const groupLabel = 'px-3 pb-1 pt-4 text-2xs uppercase text-side-dim';

function NavItem({ href, icon: Icon, current, children, ...props }: PropsWithChildren<{ href: string; icon: LucideIcon; current?: boolean; method?: 'post'; as?: 'button' }>) {
    return (
        <Link {...props} href={href} aria-current={current ? 'page' : undefined} className={`${row} ${current ? 'bg-side-pill text-on-side-pill' : rest}`}>
            <Icon className={`h-4 w-4 shrink-0 ${current ? 'text-side-accent' : ''}`} aria-hidden="true" />
            <span className="truncate">{children}</span>
        </Link>
    );
}

function GlobalSearch({ id }: { id: string }) {
    const [q, setQ] = useState(() => new URLSearchParams(window.location.search).get('q') ?? '');
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (q.trim()) router.get(route('search'), { q: q.trim() });
    };

    return (
        <form onSubmit={submit} role="search" className="relative">
            <label htmlFor={id} className="sr-only">
                Search issues
            </label>
            <input
                id={id}
                type="search"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                onKeyDown={(e) => e.key === 'Escape' && e.currentTarget.blur()}
                placeholder="Search"
                className="h-control-h w-full rounded-ctl border-side-line bg-side-hover pe-9 ps-9 text-md text-on-side placeholder:text-side-dim focus:border-side-accent focus:ring-0"
            />
            <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-side-dim" aria-hidden="true" />
            <kbd className="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 rounded-xs border border-side-line px-1.5 text-2xs text-side-dim">/</kbd>
        </form>
    );
}

/** The dark sidebar's content, shared by the fixed desktop sidebar and the sheet under 760px. */
function Sidebar({ project, searchId, create }: { project?: { key: string; name: string }; searchId: string; create: () => void }) {
    const { auth, workspace } = usePage().props;
    const user = auth.user;

    return (
        <div className="flex h-full flex-col gap-1 overflow-y-auto bg-side p-3 text-on-side">
            <WorkspaceSwitcher />

            {/* With no workspace (D11) only the account section can link anywhere. */}
            {workspace && (
                <>
                    <button type="button" onClick={create} className={`${row} mb-2 justify-center bg-primary text-on-primary`}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Create issue
                        <kbd className="rounded-xs border border-current px-1.5 text-2xs">C</kbd>
                    </button>
                    <GlobalSearch id={searchId} />

                    <nav aria-label="Main" className="mt-2 flex flex-col gap-0.5">
                        <NavItem href={route('dashboard')} icon={LayoutDashboard} current={route().current('dashboard')}>
                            Your work
                        </NavItem>
                        <NavItem href={route('projects.index')} icon={FolderKanban} current={route().current('projects.index')}>
                            Projects
                        </NavItem>
                    </nav>

                    {project && (
                        <nav aria-label="Project" className="flex flex-col gap-0.5">
                            <div className={`${groupLabel} truncate`}>{project.name}</div>
                            {projectLinks.map(([routeName, label, icon]) => (
                                <NavItem key={routeName} href={route(routeName, project.key)} icon={icon} current={route().current(routeName)}>
                                    {label}
                                </NavItem>
                            ))}
                        </nav>
                    )}
                </>
            )}

            <nav aria-label="Account" className="mt-auto flex flex-col gap-0.5">
                <div className={groupLabel}>Account</div>
                <NavItem href={route('profile.edit')} icon={UserRound} current={route().current('profile.edit')}>
                    Profile
                </NavItem>
                {workspace && (
                    <NavItem href={route('tokens.index')} icon={KeyRound} current={route().current('tokens.index')}>
                        API tokens
                    </NavItem>
                )}
                <ThemeToggle className={`${row} ${rest}`} />
                <NavItem href={route('logout')} icon={LogOut} method="post" as="button">
                    Log out
                </NavItem>
                <div className="mt-2 flex items-center gap-3 border-t border-side-line px-2 pt-3">
                    <Avatar name={user.name} email={user.email} size="sm" />
                    <div className="min-w-0">
                        <div className="truncate text-md text-on-side">{user.name}</div>
                        <div className="truncate text-sm text-side-dim">{user.email}</div>
                    </div>
                </div>
            </nav>
        </div>
    );
}

export default function Authenticated({
    header,
    project,
    children,
}: PropsWithChildren<{ header?: ReactNode; project?: { key: string; name: string } }>) {
    const [sheet, setSheet] = useState(false);
    const { workspace } = usePage().props;

    const create = () => {
        setSheet(false);
        if (!requestCreate()) router.visit(route('projects.index'));
    };

    // Global shortcuts: "/" focuses search, "c" asks the current page to open its create-issue dialog.
    // Esc closes dialogs via Headless UI. Ignored while typing or with modifier keys.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.metaKey || e.ctrlKey || e.altKey || isTyping(e.target)) return;
            if (e.key === '/') {
                e.preventDefault();
                const desktop = document.getElementById('global-search');
                if (desktop?.offsetParent) return desktop.focus();
                // Under 760px search lives in the sheet: open it, then focus once rendered.
                setSheet(true);
                setTimeout(() => document.getElementById('global-search-sheet')?.focus());
            } else if (e.key === 'c') {
                requestCreate();
            }
        };
        window.addEventListener('keydown', onKey);
        const off = router.on('navigate', () => setSheet(false));
        return () => {
            window.removeEventListener('keydown', onKey);
            off();
        };
    }, []);

    return (
        <div className="min-h-screen bg-bg md:ps-side-w">
            <aside className="fixed inset-y-0 start-0 z-30 hidden w-side-w md:block">
                <Sidebar project={project} searchId="global-search" create={create} />
            </aside>

            <div className="sticky top-0 z-30 flex h-14 items-center gap-2 bg-side px-3 text-on-side md:hidden">
                <button type="button" onClick={() => setSheet(true)} aria-label="Open menu" className="flex h-control-h w-control-h items-center justify-center rounded-ctl hover:bg-side-hover">
                    <Menu className="h-5 w-5" aria-hidden="true" />
                </button>
                <Link href="/" className="flex items-center gap-2">
                    <ApplicationLogo className="h-6 w-auto fill-current text-side-accent" />
                    <span className="text-lg">PmOps</span>
                </Link>
                <button type="button" onClick={create} disabled={!workspace} aria-label="Create issue" className="ms-auto flex h-control-h w-control-h items-center justify-center rounded-ctl bg-primary text-on-primary">
                    <Plus className="h-5 w-5" aria-hidden="true" />
                </button>
            </div>

            <AnimatePresence>
                {sheet && (
                    <Dialog static open onClose={() => setSheet(false)} className="fixed inset-0 z-40 md:hidden" aria-label="Menu">
                        <motion.div
                            aria-hidden="true"
                            className="fixed inset-0 scrim"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1, transition: transition('drawerIn') }}
                            exit={{ opacity: 0, transition: transition('drawerOut') }}
                        />
                        <DialogPanel
                            as={motion.div}
                            initial={{ x: -70, opacity: 0 }}
                            animate={{ x: 0, opacity: 1, transition: transition('drawerIn') }}
                            exit={{ x: -70, opacity: 0, transition: transition('drawerOut') }}
                            className="fixed inset-y-0 start-0 w-side-w max-w-[85vw] shadow-pop"
                        >
                            <button type="button" onClick={() => setSheet(false)} aria-label="Close menu" className="absolute end-2 top-3 z-10 flex h-control-h-sm w-control-h-sm items-center justify-center rounded-ctl text-side-dim hover:bg-side-hover">
                                <X className="h-4 w-4" aria-hidden="true" />
                            </button>
                            <Sidebar project={project} searchId="global-search-sheet" create={create} />
                        </DialogPanel>
                    </Dialog>
                )}
            </AnimatePresence>

            <main className="min-w-0">
                {header && <header className="px-4 pb-2 pt-6 md:px-8">{header}</header>}
                {children}
            </main>
            <Toaster />
        </div>
    );
}
