import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({ active = false, className = '', children, ...props }: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-2 py-2 pe-4 ps-4 text-start text-sm font-medium focus:outline-none focus-visible:bg-sunken ${
                active ? 'border-brand bg-selected text-brand' : 'border-transparent text-ink hover:bg-sunken'
            } ${className}`}
        >
            {children}
        </Link>
    );
}
