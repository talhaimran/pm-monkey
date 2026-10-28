import IssueTable from '@/Components/IssueTable';
import Pagination, { Paginated } from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Issue } from '@/types';
import { Head, router } from '@inertiajs/react';

export default function Search({ issues, filters }: { issues: Paginated<Issue>; filters: { q?: string; sort?: string } }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {filters.q ? <>Search: “{filters.q}”</> : 'All issues'}
                </h2>
            }
        >
            <Head title={filters.q ? `Search: ${filters.q}` : 'Search'} />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
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
