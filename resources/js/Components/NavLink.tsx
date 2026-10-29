import { InertiaLinkProps, Link } from '@inertiajs/react';

/** Top bar link; the active one gets a brand underline like Jira's. */
export default function NavLink({ active = false, className = '', children, ...props }: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={
                'relative inline-flex h-8 items-center rounded-[3px] px-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand ' +
                (active
                    ? 'text-brand after:absolute after:inset-x-1 after:-bottom-3 after:h-0.5 after:bg-brand'
                    : 'text-ink-subtle hover:bg-neutral hover:text-ink') +
                ' ' +
                className
            }
        >
            {children}
        </Link>
    );
}
