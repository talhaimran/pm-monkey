import IssueTable from '@/Components/IssueTable';
import Pagination, { Paginated } from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Issue } from '@/types';
import { Head, router } from '@inertiajs/react';

export default function Search({ issues, filters }: { issues: Paginated<Issue>; filters: { q?: string; sort?: string } }) {
    return (
        <AuthenticatedLayout
            header={
                <h1 className="break-words text-2xl font-semibold">{filters.q ? <>Search results for “{filters.q}”</> : 'All issues'}</h1>
            }
        >
            <Head title={filters.q ? `Search: ${filters.q}` : 'Search'} />

            <div className="space-y-4 px-4 pb-10 sm:px-6">
                <IssueTable
                    issues={issues.data}
                    sort={filters.sort}
                    onSort={(sort) => router.get(route('search'), { ...filters, sort }, { preserveState: true, replace: true })}
                />
                <Pagination page={issues} />
            </div>
        </AuthenticatedLayout>
    );
}
