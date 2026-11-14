import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({ active = false, className = '', children, ...props }: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-2 py-2 pe-4 ps-4 text-start text-sm font-medium focus-visible:bg-card-2 ${
                active ? 'border-primary bg-well text-primary' : 'border-transparent text-text hover:bg-card-2'
            } ${className}`}
        >
            {children}
        </Link>
    );
}
