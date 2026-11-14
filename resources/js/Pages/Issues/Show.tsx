import IssueView, { IssueViewData } from '@/Components/IssueView';
import { ProjectHeader } from '@/Components/ProjectNav';
import { issueUrl } from '@/issueUrl';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ActivityEntry, IssueLink } from '@/types';
import { Head } from '@inertiajs/react';

/** The full issue page, the canonical view of an issue. The peek drawer shows the same IssueView in less room. */
export default function Show({ issue, project, activity, options, can }: IssueViewData & { project: { key: string; name: string }; activity: ActivityEntry[] }) {
    const crumbs = [issue.epic, issue.parent].filter((l): l is IssueLink => !!l).map((l) => ({ label: l.key, href: issueUrl(l.key) }));

    return (
        <AuthenticatedLayout project={project} header={<ProjectHeader project={project} crumbs={[...crumbs, { label: issue.key, href: issueUrl(issue.key) }]} />}>
            <Head title={`${issue.key} ${issue.title}`} />
            <div className="px-4 pb-12 pt-2 md:px-8">
                <IssueView key={issue.key} issue={issue} options={options} can={can} activity={activity} full />
            </div>
        </AuthenticatedLayout>
    );
}
