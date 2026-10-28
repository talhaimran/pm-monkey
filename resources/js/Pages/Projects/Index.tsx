import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
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
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Projects
                </h2>
            }
        >
            <Head title="Projects" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {invitations.length > 0 && (
                    <section className="rounded-lg bg-indigo-50 p-4 shadow-sm dark:bg-indigo-950">
                        <h3 className="font-medium text-gray-900 dark:text-gray-100">
                            Pending invitations
                        </h3>
                        {!verified && (
                            <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                Verify your email address to accept invitations.
                                {linkSent ? (
                                    <span className="font-medium">Verification link sent; check your inbox.</span>
                                ) : (
                                    <button
                                        type="button"
                                        className="text-indigo-700 underline dark:text-indigo-300"
                                        onClick={() =>
                                            router.post(route('verification.send'), {}, { preserveScroll: true, onSuccess: () => setLinkSent(true) })
                                        }
                                    >
                                        Send verification link
                                    </button>
                                )}
                            </p>
                        )}
                        <ul className="mt-2 divide-y divide-indigo-100 dark:divide-indigo-900">
                            {invitations.map((inv) => (
                                <li
                                    key={inv.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm text-gray-700 dark:text-gray-300"
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

                <section className="rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800">
                    <h3 className="font-medium text-gray-900 dark:text-gray-100">
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

                <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    {projects.length === 0 ? (
                        <p className="p-6 text-sm text-gray-500 dark:text-gray-400">
                            You're not in any projects yet. Create one above.
                        </p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {projects.map((p) => (
                                <li key={p.id}>
                                    <Link
                                        href={route('projects.show', p.key)}
                                        className="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 sm:px-6 dark:hover:bg-gray-700"
                                    >
                                        <span className="w-16 font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                                            {p.key}
                                        </span>
                                        <span className="flex-1 text-gray-900 dark:text-gray-100">
                                            {p.name}
                                        </span>
                                        <span className="text-xs uppercase text-gray-500 dark:text-gray-400">
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
