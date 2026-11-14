import EmptyState from '@/Components/EmptyState';
import { FolderKanban } from 'lucide-react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { ProjectAvatar } from '@/Components/ProjectNav';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Invitation, Project } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Index({
    projects,
    invitations,
}: {
    projects: Project[];
    invitations: Invitation[];
}) {
    const { data, setData, post, errors, processing } = useForm({
        key: '',
        name: '',
    });
    const verified = Boolean(usePage().props.auth.user.email_verified_at);
    const [linkSent, setLinkSent] = useState(false);

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h1 className="text-display text-text">
                    Projects
                </h1>
            }
        >
            <Head title="Projects" />

            <div className="space-y-6 px-4 pb-10 md:px-8">
                {invitations.length > 0 && (
                    <section className="rounded-card tint-info p-5">
                        <h3 className="text-lg text-text">
                            Pending invitations
                        </h3>
                        {!verified && (
                            <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-text">
                                Verify your email address to accept invitations.
                                {linkSent ? (
                                    <span className="font-medium">Verification link sent; check your inbox.</span>
                                ) : (
                                    <button
                                        type="button"
                                        className="text-primary underline"
                                        onClick={() =>
                                            router.post(route('verification.send'), {}, { preserveScroll: true, onSuccess: () => setLinkSent(true) })
                                        }
                                    >
                                        Send verification link
                                    </button>
                                )}
                            </p>
                        )}
                        <ul className="mt-2 divide-y divide-line">
                            {invitations.map((inv) => (
                                <li
                                    key={inv.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm text-text"
                                >
                                    <span>
                                        <strong>{inv.project?.name}</strong> (
                                        {inv.project?.key}) as {inv.role}
                                    </span>
                                    <PrimaryButton
                                        disabled={!verified}
                                        onClick={() =>
                                            router.post(
                                                route('invitations.accept', inv.id),
                                            )
                                        }
                                    >
                                        Accept
                                    </PrimaryButton>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="rounded-card bg-card p-5 shadow sm:p-6">
                    <h3 className="text-lg text-text">
                        New project
                    </h3>
                    <form
                        onSubmit={create}
                        className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start"
                    >
                        <div className="sm:w-40">
                            <InputLabel htmlFor="key" value="Key" />
                            <TextInput
                                id="key"
                                className="mt-1 block w-full uppercase"
                                value={data.key}
                                maxLength={10}
                                placeholder="WEB"
                                onChange={(e) =>
                                    setData('key', e.target.value.toUpperCase())
                                }
                                required
                            />
                            <InputError className="mt-2" message={errors.key} />
                        </div>
                        <div className="flex-1">
                            <InputLabel htmlFor="name" value="Name" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                placeholder="Website"
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>
                        <PrimaryButton className="sm:mt-6" disabled={processing}>
                            Create
                        </PrimaryButton>
                    </form>
                </section>

                <section className="overflow-hidden rounded-card bg-card shadow">
                    {projects.length === 0 ? (
                        <div className="p-5">
                            <EmptyState icon={FolderKanban} title="No projects yet." hint="Create your first project with the form above." />
                        </div>
                    ) : (
                        <ul className="divide-y divide-line">
                            {projects.map((p) => (
                                <li key={p.id}>
                                    <Link
                                        href={route('projects.show', p.key)}
                                        className="flex items-center gap-4 px-4 py-3 hover:bg-card-2 md:px-8"
                                    >
                                        <ProjectAvatar projectKey={p.key} />
                                        <span className="min-w-0 flex-1 truncate font-medium text-primary">
                                            {p.name}
                                        </span>
                                        <span className="w-16 text-muted">
                                            {p.key}
                                        </span>
                                        <span className="text-xs uppercase text-muted">
                                            {p.my_role}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
