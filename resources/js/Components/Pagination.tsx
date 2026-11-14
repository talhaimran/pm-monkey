import { Link } from '@inertiajs/react';

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null };
}

export default function Pagination({ page }: { page: Paginated<unknown> }) {
    const link = (href: string | null, label: string) =>
        href ? (
            <Link href={href} preserveScroll className="flex h-8 items-center rounded-ctl border border-line-2 bg-card px-3 text-md text-text hover:bg-card-2">
                {label}
            </Link>
        ) : (
            <span className="flex h-8 items-center rounded-ctl px-3 text-md text-muted opacity-50">{label}</span>
        );

    return (
        <div className="flex items-center justify-between text-muted">
            <span>{page.meta.total ? `${page.meta.from}–${page.meta.to} of ${page.meta.total}` : 'No issues'}</span>
            <span className="flex gap-2">
                {link(page.links.prev, 'Previous')}
                {link(page.links.next, 'Next')}
            </span>
        </div>
    );
}
