import { Link } from '@inertiajs/react';

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null };
}

export default function Pagination({ page }: { page: Paginated<unknown> }) {
    const link = (href: string | null, label: string) =>
        href ? (
            <Link href={href} preserveScroll className="rounded border border-gray-300 px-3 py-1 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                {label}
            </Link>
        ) : (
            <span className="rounded border border-gray-200 px-3 py-1 text-gray-400 dark:border-gray-700">{label}</span>
        );

    return (
        <div className="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
            <span>{page.meta.total ? `${page.meta.from}–${page.meta.to} of ${page.meta.total}` : 'No issues'}</span>
            <span className="flex gap-2">
                {link(page.links.prev, 'Previous')}
                {link(page.links.next, 'Next')}
            </span>
        </div>
    );
}
