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
    expires_at: string | null;
    created_at: string;
}

const card = 'rounded border border-line bg-surface p-4 sm:p-6 ';

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
    const form = useForm({ name: '', access: 'read' as 'read' | 'write', projects: [] as string[], expires_in_days: '90' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            projects: allProjects ? null : d.projects,
            expires_in_days: d.expires_in_days === '' ? null : Number(d.expires_in_days),
        }));
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
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold text-ink">API tokens</h1>}>
            <Head title="API tokens" />

            <div className="max-w-4xl space-y-6 px-4 pb-10 sm:px-6">
                <section className={card}>
                    <p className="text-sm text-ink-subtle">
                        Tokens let scripts and AI agents use PM Monkey as you, through the REST API or MCP. Send a token as{' '}
                        <code className="rounded bg-sunken px-1">Authorization: Bearer &lt;token&gt;</code>.
                    </p>
                    <dl className="mt-3 grid gap-1 text-sm sm:grid-cols-[8rem_1fr]">
                        <dt className="text-ink-subtle">REST API</dt>
                        <dd className="font-mono text-ink">{endpoints.api}</dd>
                        <dt className="text-ink-subtle">API docs</dt>
                        <dd>
                            <a href={endpoints.docs} className="text-brand underline">
                                {endpoints.docs}
                            </a>
                        </dd>
                        <dt className="text-ink-subtle">MCP (HTTP)</dt>
                        <dd className="font-mono text-ink">{endpoints.mcp}</dd>
                    </dl>
                </section>

                {flashToken && (
                    <section className="rounded-lg border border-success bg-loz-done p-4" role="status">
                        <h3 className="font-medium text-success">Copy your new token now. It won't be shown again.</h3>
                        <div className="mt-2 flex gap-2">
                            <input
                                readOnly
                                aria-label="New API token"
                                value={flashToken}
                                onFocus={(e) => e.target.select()}
                                className="flex-1 rounded-[3px] border-input-line bg-surface font-mono text-sm text-ink"
                            />
                            <SecondaryButton onClick={copy}>{copied ? 'Copied' : 'Copy'}</SecondaryButton>
                        </div>
                    </section>
                )}

                <section className={card}>
                    <h3 className="text-base font-semibold text-ink">Create a token</h3>
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
                            <legend className="text-sm font-medium text-ink">Access</legend>
                            <div className="mt-1 flex flex-wrap gap-4 text-sm text-ink">
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
                            <legend className="text-sm font-medium text-ink">Projects</legend>
                            <label className="mt-1 flex items-center gap-2 text-sm text-ink">
                                <input type="checkbox" className="rounded" checked={allProjects} onChange={(e) => setAllProjects(e.target.checked)} />
                                All my projects (including ones I join later)
                            </label>
                            {!allProjects && (
                                <div className="mt-2 flex flex-wrap gap-3 text-sm text-ink">
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

                        <div>
                            <InputLabel htmlFor="token-expiry" value="Expires" />
                            <select
                                id="token-expiry"
                                className="mt-1 rounded-[3px] border-input-line bg-surface text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand"
                                value={form.data.expires_in_days}
                                onChange={(e) => form.setData('expires_in_days', e.target.value)}
                            >
                                <option value="30">In 30 days</option>
                                <option value="90">In 90 days</option>
                                <option value="365">In 1 year</option>
                                <option value="">Never</option>
                            </select>
                            <InputError className="mt-1" message={form.errors.expires_in_days} />
                        </div>

                        <PrimaryButton disabled={form.processing || (!allProjects && form.data.projects.length === 0)}>Create token</PrimaryButton>
                    </form>
                </section>

                <section className={card}>
                    <h3 className="text-base font-semibold text-ink">Your tokens</h3>
                    {tokens.length === 0 ? (
                        <p className="mt-2 text-sm text-ink-subtle">No tokens yet.</p>
                    ) : (
                        <ul className="mt-3 divide-y divide-line">
                            {tokens.map((t) => (
                                <li key={t.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 text-sm">
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium text-ink">{t.name}</div>
                                        <div className="text-xs text-ink-subtle">
                                            {t.access === 'write' ? 'Read and write' : 'Read-only'} ·{' '}
                                            {t.projects === null ? 'All projects' : t.projects.length ? t.projects.join(', ') : 'No projects left'} · Last used{' '}
                                            {t.last_used_at ? new Date(t.last_used_at).toLocaleString() : 'never'} · Created{' '}
                                            {new Date(t.created_at).toLocaleDateString()} ·{' '}
                                            {t.expires_at === null ? (
                                                'Never expires'
                                            ) : new Date(t.expires_at) < new Date() ? (
                                                <span className="font-medium text-danger">Expired</span>
                                            ) : (
`Expires ${new Date(t.expires_at).toLocaleDateString()}`
                                            )}
                                        </div>
                                    </div>
                                    <DangerButton
                                        onClick={() =>
                                            confirm(`Revoke"${t.name}"? Anything using it stops working immediately.`) &&
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
