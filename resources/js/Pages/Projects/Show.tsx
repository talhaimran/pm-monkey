import IssueFormModal from '@/Components/IssueFormModal';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Issue, IssueOptions, Project } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    project,
    issues,
    options,
    can,
}: {
    project: Project;
    issues: Issue[];
    options: IssueOptions;
    can: { contribute: boolean };
}) {
    const [creating, setCreating] = useState(false);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        <span className="font-mono text-indigo-600 dark:text-indigo-400">{project.key}</span>{' '}
                        {project.name}
                    </h2>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('projects.settings', project.key)}
                            className="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Settings
                        </Link>
                        {can.contribute && <PrimaryButton onClick={() => setCreating(true)}>Create issue</PrimaryButton>}
                    </div>
                </div>
            }
        >
            <Head title={project.name} />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <h3 className="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-500 sm:px-6 dark:border-gray-700 dark:text-gray-400">
                        Recent issues
                    </h3>
                    {issues.length === 0 ? (
                        <p className="p-6 text-sm text-gray-500 dark:text-gray-400">No issues yet.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {issues.map((i) => (
                                <li key={i.id}>
                                    <Link
                                        href={route('issues.show', i.key)}
                                        className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm hover:bg-gray-50 sm:px-6 dark:hover:bg-gray-700"
                                    >
                                        <span className="w-20 font-mono text-indigo-600 dark:text-indigo-400">{i.key}</span>
                                        <span className="min-w-0 flex-1 truncate text-gray-900 dark:text-gray-100">{i.title}</span>
                                        <span className="text-xs uppercase text-gray-500 dark:text-gray-400">{i.type}</span>
                                        <span className="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                            {i.status}
                                        </span>
                                        <span className="w-32 truncate text-xs text-gray-500 dark:text-gray-400">
                                            {i.assignee?.name ?? 'Unassigned'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {can.contribute && (
                <IssueFormModal show={creating} onClose={() => setCreating(false)} projectKey={project.key} options={options} />
            )}
        </AuthenticatedLayout>
    );
}
