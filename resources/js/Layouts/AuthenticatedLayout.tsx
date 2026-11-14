import { Menu, Plus, Search, X } from 'lucide-react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Avatar from '@/Components/Avatar';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import PrimaryButton from '@/Components/PrimaryButton';
import ProjectNav from '@/Components/ProjectNav';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import { requestCreate } from '@/Components/useCreateShortcut';
import { Link, router, usePage } from '@inertiajs/react';
import { FormEvent, PropsWithChildren, ReactNode, useEffect, useState } from 'react';

const isTyping = (el: EventTarget | null) =>
    el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

function GlobalSearch({ id, className = '' }: { id: string; className?: string }) {
    const [q, setQ] = useState(() => new URLSearchParams(window.location.search).get('q') ?? '');
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (q.trim()) router.get(route('search'), { q: q.trim() });
    };

    return (
        <form onSubmit={submit} role="search" className={`relative ${className}`}>
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
                className="w-full rounded-ctl border-line-2 bg-bg py-1 pe-8 ps-8 text-sm text-text placeholder:text-muted hover:bg-card-2 focus:border-primary focus:ring-1"
            />
            <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
            <kbd className="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2 rounded border border-line px-1 text-[11px] text-muted">/</kbd>
        </form>
    );
}

export default function Authenticated({
    header,
    project,
    children,
}: PropsWithChildren<{ header?: ReactNode; project?: { key: string; name: string } }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const create = () => {
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
                // Narrow screens keep search in the menu: open it, then focus once rendered.
                setShowingNavigationDropdown(true);
                setTimeout(() => document.getElementById('global-search-mobile')?.focus());
            } else if (e.key === 'c') {
                requestCreate();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const plus = (
        <Plus className="h-4 w-4" aria-hidden="true" />
    );

    return (
        <div className="min-h-screen bg-bg">
            <nav className="sticky top-0 z-30 border-b border-line bg-bg">
                <div className="flex h-14 items-center gap-1 px-3 sm:px-4">
                    <Link href="/" className="me-3 flex shrink-0 items-center gap-2 rounded px-1 py-1 hover:bg-card-2">
                        <ApplicationLogo className="h-6 w-auto fill-current text-primary" />
                        <span className="hidden font-semibold text-text md:inline">PmOps</span>
                    </Link>

                    <div className="hidden h-full items-center gap-1 sm:flex">
                        <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                            Your work
                        </NavLink>
                        <NavLink href={route('projects.index')} active={route().current('projects.*') || route().current('issues.*')}>
                            Projects
                        </NavLink>
                        <PrimaryButton onClick={create} className="ms-2" title="Create (c)">
                            {plus} Create
                        </PrimaryButton>
                    </div>

                    <div className="ms-auto hidden items-center gap-1 sm:flex">
                        <GlobalSearch id="global-search" className="w-48 lg:w-72" />
                        <ThemeToggle />
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button type="button" className="rounded-full p-1 hover:bg-card-2" aria-label="Account menu">
                                    <Avatar name={user.name} email={user.email} />
                                </button>
                            </Dropdown.Trigger>

                            <Dropdown.Content>
                                <div className="border-b border-line px-4 py-2">
                                    <div className="truncate font-semibold text-text">{user.name}</div>
                                    <div className="truncate text-xs text-muted">{user.email}</div>
                                </div>
                                <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                <Dropdown.Link href={route('tokens.index')}>API tokens</Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    Log out
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>

                    <div className="ms-auto flex items-center gap-1 sm:hidden">
                        <PrimaryButton onClick={create} aria-label="Create">
                            {plus}
                        </PrimaryButton>
                        <ThemeToggle />
                        <button
                            onClick={() => setShowingNavigationDropdown((previousState) => !previousState)}
                            aria-label="Menu"
                            aria-expanded={showingNavigationDropdown}
                            className="inline-flex items-center justify-center rounded p-2 text-muted hover:bg-card-2"
                        >
                            {showingNavigationDropdown ? <X className="h-5 w-5" aria-hidden="true" /> : <Menu className="h-5 w-5" aria-hidden="true" />}
                        </button>
                    </div>
                </div>

                <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' border-t border-line sm:hidden'}>
                    <div className="space-y-1 pb-3 pt-2">
                        <GlobalSearch id="global-search-mobile" className="px-4 pb-2" />
                        <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                            Your work
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('projects.index')} active={route().current('projects.index')}>
                            Projects
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-line pb-1 pt-4">
                        <div className="flex items-center gap-3 px-4">
                            <Avatar name={user.name} email={user.email} size="md" />
                            <div className="min-w-0">
                                <div className="truncate font-medium text-text">{user.name}</div>
                                <div className="truncate text-xs text-muted">{user.email}</div>
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                            <ResponsiveNavLink href={route('tokens.index')}>API tokens</ResponsiveNavLink>
                            <ResponsiveNavLink method="post" href={route('logout')} as="button">
                                Log out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <div className="flex">
                {project && (
                    <aside className="sticky top-14 hidden h-[calc(100vh-3.5rem)] w-60 shrink-0 overflow-y-auto border-e border-line bg-card-2 lg:block">
                        <ProjectNav projectKey={project.key} name={project.name} />
                    </aside>
                )}

                <main className="min-w-0 flex-1">
                    {project && (
                        // Narrow screens: the project's sections become a tab row, since the sidebar is hidden.
                        <div className="border-b border-line px-4 lg:hidden">
                            <ProjectNav projectKey={project.key} name={project.name} compact />
                        </div>
                    )}
                    {header && <header className="px-4 pb-2 pt-5 sm:px-6">{header}</header>}
                    {children}
                </main>
            </div>
        </div>
    );
}
