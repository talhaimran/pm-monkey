import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Token {
    id: number;
    name: string;
    access: 'read' | 'write';
    projects: string[] | null;
    last_used_at: string | null;
    created_at: string;
}

const card = 'rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800';

export default function Index({
    tokens,
    projects,
    endpoints,
}: {
    tokens: Token[];
    projects: { key: string; name: string }[];
    endpoints: { api: string; docs: string; mcp: string };
}) {
    const flashToken = (usePage().props as { flash?: { token?: string | null } }).flash?.token;
    const [copied, setCopied] = useState(false);
    const [allProjects, setAllProjects] = useState(true);
    const form = useForm({ name: '', access: 'read' as 'read' | 'write', projects: [] as string[] });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, projects: allProjects ? null : d.projects }));
        form.post(route('tokens.store'), { preserveScroll: true, onSuccess: () => form.reset('name', 'projects') });
    };

    const toggleProject = (key: string) =>
        form.setData('projects', form.data.projects.includes(key) ? form.data.projects.filter((k) => k !== key) : [...form.data.projects, key]);

    const copy = async () => {
        if (!flashToken) return;
        await navigator.clipboard.writeText(flashToken);
        setCopied(true);
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">API tokens</h2>}>
            <Head title="API tokens" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className={card}>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Tokens let scripts and AI agents use PM Monkey as you, through the REST API or MCP. Send a token as{' '}
                        <code className="rounded bg-gray-100 px-1 dark:bg-gray-700">Authorization: Bearer &lt;token&gt;</code>.
                    </p>
                    <dl className="mt-3 grid gap-1 text-sm sm:grid-cols-[8rem_1fr]">
                        <dt className="text-gray-500">REST API</dt>
                        <dd className="font-mono text-gray-800 dark:text-gray-200">{endpoints.api}</dd>
                        <dt className="text-gray-500">API docs</dt>
                        <dd>
                            <a href={endpoints.docs} className="text-indigo-600 underline dark:text-indigo-400">
                                {endpoints.docs}
                            </a>
                        </dd>
                        <dt className="text-gray-500">MCP (HTTP)</dt>
                        <dd className="font-mono text-gray-800 dark:text-gray-200">{endpoints.mcp}</dd>
                    </dl>
                </section>

                {flashToken && (
                    <section className="rounded-lg border border-green-300 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950" role="status">
                        <h3 className="font-medium text-green-900 dark:text-green-200">Copy your new token now. It won't be shown again.</h3>
                        <div className="mt-2 flex gap-2">
                            <input
                                readOnly
                                aria-label="New API token"
                                value={flashToken}
                                onFocus={(e) => e.target.select()}
                                className="flex-1 rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                            />
                            <SecondaryButton onClick={copy}>{copied ? 'Copied' : 'Copy'}</SecondaryButton>
                        </div>
                    </section>
                )}

                <section className={card}>
                    <h3 className="font-medium text-gray-900 dark:text-gray-100">Create a token</h3>
                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <div>
                            <InputLabel htmlFor="token-name" value="Name" />
                            <TextInput
                                id="token-name"
                                className="mt-1 block w-full"
                                placeholder="e.g. Claude Desktop"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            <InputError className="mt-1" message={form.errors.name} />
                        </div>

                        <fieldset>
                            <legend className="text-sm font-medium text-gray-700 dark:text-gray-300">Access</legend>
                            <div className="mt-1 flex flex-wrap gap-4 text-sm text-gray-700 dark:text-gray-300">
                                {(['read', 'write'] as const).map((a) => (
                                    <label key={a} className="flex items-center gap-2">
                                        <input type="radio" name="access" checked={form.data.access === a} onChange={() => form.setData('access', a)} />
                                        {a === 'read' ? 'Read-only' : 'Read and write'}
                                    </label>
                                ))}
                            </div>
                            <InputError className="mt-1" message={form.errors.access} />
                        </fieldset>

                        <fieldset>
                            <legend className="text-sm font-medium text-gray-700 dark:text-gray-300">Projects</legend>
                            <label className="mt-1 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" className="rounded" checked={allProjects} onChange={(e) => setAllProjects(e.target.checked)} />
                                All my projects (including ones I join later)
                            </label>
                            {!allProjects && (
                                <div className="mt-2 flex flex-wrap gap-3 text-sm text-gray-700 dark:text-gray-300">
                                    {projects.map((p) => (
                                        <label key={p.key} className="flex items-center gap-2">
                                            <input type="checkbox" className="rounded" checked={form.data.projects.includes(p.key)} onChange={() => toggleProject(p.key)} />
                                            <span className="font-mono">{p.key}</span> {p.name}
                                        </label>
                                    ))}
                                </div>
                            )}
                            <InputError className="mt-1" message={form.errors.projects} />
                        </fieldset>

                        <PrimaryButton disabled={form.processing || (!allProjects && form.data.projects.length === 0)}>Create token</PrimaryButton>
                    </form>
                </section>

                <section className={card}>
                    <h3 className="font-medium text-gray-900 dark:text-gray-100">Your tokens</h3>
                    {tokens.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500">No tokens yet.</p>
                    ) : (
                        <ul className="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                            {tokens.map((t) => (
                                <li key={t.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 text-sm">
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium text-gray-900 dark:text-gray-100">{t.name}</div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            {t.access === 'write' ? 'Read and write' : 'Read-only'} ·{' '}
                                            {t.projects === null ? 'All projects' : t.projects.length ? t.projects.join(', ') : 'No projects left'} · Last used{' '}
                                            {t.last_used_at ? new Date(t.last_used_at).toLocaleString() : 'never'} · Created{' '}
                                            {new Date(t.created_at).toLocaleDateString()}
                                        </div>
                                    </div>
                                    <DangerButton
                                        onClick={() =>
                                            confirm(`Revoke "${t.name}"? Anything using it stops working immediately.`) &&
                                            router.delete(route('tokens.destroy', t.id), { preserveScroll: true })
                                        }
                                    >
                                        Revoke
                                    </DangerButton>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
