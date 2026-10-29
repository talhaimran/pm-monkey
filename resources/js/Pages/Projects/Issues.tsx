import IssueFilters, { Filters } from '@/Components/IssueFilters';
import IssueFormModal from '@/Components/IssueFormModal';
import IssueTable from '@/Components/IssueTable';
import Pagination, { Paginated } from '@/Components/Pagination';
import PrimaryButton from '@/Components/PrimaryButton';
import { ProjectHeader } from '@/Components/ProjectNav';
import { useCreateShortcut } from '@/Components/useCreateShortcut';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Issue, IssueOptions, Project } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

export default function Issues({
    project,
    issues,
    filters,
    options,
    can,
}: {
    project: Project;
    issues: Paginated<Issue>;
    filters: Filters;
    options: IssueOptions;
    can: { contribute: boolean };
}) {
    const [creating, setCreating] = useState(false);
    const openCreate = useCallback(() => setCreating(true), []);
    useCreateShortcut(openCreate, can.contribute);

    // Changing filters or sort always returns to page 1.
    const apply = (next: Filters) =>
        router.get(
            route('projects.issues.index', project.key),
            Object.fromEntries(Object.entries({ ...filters, ...next }).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AuthenticatedLayout
            project={project}
            header={
                <ProjectHeader project={project} title="Issues">
                    {can.contribute && <PrimaryButton onClick={openCreate}>Create issue</PrimaryButton>}
                </ProjectHeader>
            }
        >
            <Head title={`${project.key} issues`} />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <IssueFilters
                    filters={filters}
                    options={options}
                    onApply={apply}
                    onClear={() => router.get(route('projects.issues.index', project.key), filters.sort ? { sort: filters.sort } : {}, { replace: true })}
                />
                <IssueTable issues={issues.data} sort={filters.sort} onSort={(sort) => apply({ sort })} />
                <Pagination page={issues} />
            </div>

            {can.contribute && <IssueFormModal show={creating} onClose={() => setCreating(false)} projectKey={project.key} options={options} />}
        </AuthenticatedLayout>
    );
}
