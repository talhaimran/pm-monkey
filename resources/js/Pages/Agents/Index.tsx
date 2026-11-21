import AgentLabel from '@/Components/AgentLabel';
import Avatar from '@/Components/Avatar';
import Checkbox from '@/Components/Checkbox';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { selectClass } from '@/Components/IssueFormModal';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import WarningConfirm from '@/Components/WarningConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Bot, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Level = 'read' | 'write' | 'delete';

interface AgentRow {
    id: number;
    name: string;
    description: string | null;
    level: Level;
    status: 'active' | 'paused';
    projects: string[] | null;
    owner: { id: number; name: string; email: string };
    last_used_at: string | null;
    expires_at: string | null;
}

const levels: Record<Level, { label: string; hint: string; tint: string }> = {
    read: { label: 'Read only', hint: 'Looks at issues and projects, changes nothing.', tint: 'tint-slate' },
    write: { label: 'Read and write', hint: 'Creates and edits issues, comments and moves them, except to Done.', tint: 'tint-sky' },
    delete: { label: 'Read, write and delete', hint: 'Also deletes issues, comments and other things. Off unless you turn it on.', tint: 'tint-amber' },
};
const card = 'rounded-card bg-card p-5 shadow sm:p-6';
const small = 'inline-flex h-control-h-sm items-center rounded-ctl px-2.5 text-sm text-text hover:bg-card-2 disabled:opacity-50';

const when = (v: string | null, fallback: string) => (v ? new Date(v).toLocaleDateString() : fallback);

function Pill({ tint, children }: { tint: string; children: string }) {
    return <span className={`inline-flex h-pill-h items-center whitespace-nowrap rounded-pill px-2.5 text-xs font-semibold ${tint}`}>{children}</span>;
}

export default function Index({
    agents,
    others,
    projects,
    agentsEnabled,
    can,
    endpoints,
}: {
    agents: AgentRow[];
    others: AgentRow[];
    projects: { key: string; name: string }[];
    agentsEnabled: boolean;
    can: { manageAll: boolean };
    endpoints: { api: string; docs: string; mcp: string };
}) {
    const props = usePage().props as unknown as { flash?: { token?: string | null; tokenAgent?: string | null }; workspace?: { name: string } | null };
    const flashToken = props.flash?.token;
    const workspaceName = props.workspace?.name ?? 'this workspace';
    const [editing, setEditing] = useState<AgentRow | 'new' | null>(null);
    const [revoking, setRevoking] = useState<AgentRow | null>(null);
    const [regenerating, setRegenerating] = useState<AgentRow | null>(null);
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        if (!flashToken) return;
        await navigator.clipboard.writeText(flashToken);
        setCopied(true);
    };
    const act = (verb: 'pause' | 'resume', a: AgentRow) => router.post(route(`agents.${verb}`, a.id), {}, { preserveScroll: true });

    return (
        <AuthenticatedLayout header={<h1 className="text-display text-text">Agents</h1>}>
            <Head title="Agents" />

            <div className="max-w-5xl space-y-6 px-4 pb-10 md:px-8">
                <section className={card}>
                    <p className="text-sm text-muted">
                        An agent is Claude Code, Codex or a script that works in {workspaceName} as you, with a level you choose. Each agent has its own token for the REST API or MCP. Send it as{' '}
                        <code className="rounded-ctl bg-card-2 px-1">Authorization: Bearer &lt;token&gt;</code>.
                    </p>
                    <dl className="mt-3 grid gap-1 text-sm sm:grid-cols-[8rem_1fr]">
                        <dt className="text-muted">REST API</dt>
                        <dd className="font-mono text-text">{endpoints.api}</dd>
                        <dt className="text-muted">API docs</dt>
                        <dd>
                            <a href={endpoints.docs} className="text-primary underline">
                                {endpoints.docs}
                            </a>
                        </dd>
                        <dt className="text-muted">MCP (HTTP)</dt>
                        <dd className="font-mono text-text">{endpoints.mcp}</dd>
                    </dl>
                </section>

                {!agentsEnabled && (
                    <p role="status" className="rounded-card tint-warn p-4 text-sm">
                        Agents are turned off in {workspaceName}. Every agent call is refused until an Owner or Admin turns them back on in Settings → Security.
                    </p>
                )}

                {flashToken && (
                    <section className="rounded-card border border-c-good tint-good p-4" role="status">
                        <h2 className="font-medium text-t-good">Copy the token{props.flash?.tokenAgent ? ` for ${props.flash.tokenAgent}` : ''} now. It won’t be shown again.</h2>
                        <div className="mt-2 flex gap-2">
                            <input
                                readOnly
                                aria-label="New agent token"
                                value={flashToken}
                                onFocus={(e) => e.target.select()}
                                className="h-control-h min-w-0 flex-1 rounded-ctl border-line-2 bg-bg font-mono text-sm text-text"
                            />
                            <SecondaryButton onClick={copy}>{copied ? 'Copied' : 'Copy'}</SecondaryButton>
                        </div>
                    </section>
                )}

                <section className={card}>
                    <div className="flex items-center gap-3">
                        <h2 className="flex-1 text-lg text-text">Your agents</h2>
                        <PrimaryButton onClick={() => setEditing('new')} disabled={!agentsEnabled}>
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            New agent
                        </PrimaryButton>
                    </div>
                    {agents.length === 0 ? (
                        <div className="mt-4">
                            <EmptyState icon={Bot} title={`No agents yet. Create one to let Claude Code, Codex or a script work in ${workspaceName}.`} />
                        </div>
                    ) : (
                        <AgentTable rows={agents} mine onEdit={setEditing} onAct={act} onRevoke={setRevoking} onRegenerate={setRegenerating} />
                    )}
                </section>

                {can.manageAll && (
                    <section className={card}>
                        <h2 className="text-lg text-text">All agents in {workspaceName}</h2>
                        <p className="mt-1 text-sm text-muted">As an Owner or Admin you can pause, resume or revoke anyone’s agent. Only its owner can edit it or make a new token.</p>
                        {others.length === 0 ? (
                            <div className="mt-4">
                                <EmptyState icon={Bot} title="Nobody else has an agent here yet." />
                            </div>
                        ) : (
                            <AgentTable rows={others} mine={false} onEdit={setEditing} onAct={act} onRevoke={setRevoking} onRegenerate={setRegenerating} />
                        )}
                    </section>
                )}
            </div>

            <AgentForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} agent={editing} projects={projects} onClose={() => setEditing(null)} />

            <WarningConfirm
                open={revoking !== null}
                title={`Revoke ${revoking?.name ?? ''}?`}
                warnings={[
                    { text: 'Anything using its token stops working at once.', reason: 'A revoked agent can’t be brought back; create a new one instead.' },
                    { text: 'Its past work stays in the history under its name.' },
                ]}
                confirmLabel="Revoke agent"
                onCancel={() => setRevoking(null)}
                onConfirm={() => revoking && router.delete(route('agents.revoke', revoking.id), { preserveScroll: true, onFinish: () => setRevoking(null) })}
            />
            <WarningConfirm
                open={regenerating !== null}
                title={`Make a new token for ${regenerating?.name ?? ''}?`}
                warnings={[{ text: 'The old token stops working at once.', reason: 'Whatever uses it has to be given the new one.' }]}
                confirmLabel="Make new token"
                onCancel={() => setRegenerating(null)}
                onConfirm={() => regenerating && router.post(route('agents.regenerate', regenerating.id), {}, { preserveScroll: true, onFinish: () => setRegenerating(null) })}
            />
        </AuthenticatedLayout>
    );
}

function AgentTable({
    rows,
    mine,
    onEdit,
    onAct,
    onRevoke,
    onRegenerate,
}: {
    rows: AgentRow[];
    mine: boolean;
    onEdit: (a: AgentRow) => void;
    onAct: (verb: 'pause' | 'resume', a: AgentRow) => void;
    onRevoke: (a: AgentRow) => void;
    onRegenerate: (a: AgentRow) => void;
}) {
    return (
        <div className="mt-3 overflow-x-auto">
            <table className="min-w-full text-left text-md">
                <thead className="border-b border-line text-xs text-muted">
                    <tr>
                        {['Agent', 'Level', 'Projects', 'Status', 'Last used', 'Expires', ''].map((h, i) => (
                            <th key={i} scope="col" className="px-3 py-2 font-normal">
                                {h || <span className="sr-only">Actions</span>}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {rows.map((a) => {
                        const expired = a.expires_at !== null && new Date(a.expires_at) < new Date();
                        return (
                            <tr key={a.id} className="align-top">
                                <td className="min-w-[12rem] px-3 py-2">
                                    <AgentLabel agent={{ name: a.name, owner: a.owner.name }} />
                                    {a.description && <div className="mt-0.5 text-sm text-muted">{a.description}</div>}
                                </td>
                                <td className="px-3 py-2">
                                    <Pill tint={levels[a.level].tint}>{levels[a.level].label}</Pill>
                                </td>
                                <td className="px-3 py-2 text-text">{a.projects === null ? 'All projects' : a.projects.length ? a.projects.join(', ') : 'No projects left'}</td>
                                <td className="px-3 py-2">
                                    <Pill tint={a.status === 'active' ? 'tint-good' : 'tint-warn'}>{a.status === 'active' ? 'Active' : 'Paused'}</Pill>
                                </td>
                                <td className="whitespace-nowrap px-3 py-2 text-muted">{a.last_used_at ? new Date(a.last_used_at).toLocaleString() : 'Never'}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-muted">{expired ? <span className="font-medium text-t-bad">Expired</span> : when(a.expires_at, 'Never')}</td>
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap justify-end gap-1">
                                        {mine && (
                                            <button type="button" className={small} onClick={() => onEdit(a)}>
                                                Edit<span className="sr-only"> {a.name}</span>
                                            </button>
                                        )}
                                        <button type="button" className={small} onClick={() => onAct(a.status === 'active' ? 'pause' : 'resume', a)}>
                                            {a.status === 'active' ? 'Pause' : 'Resume'}
                                            <span className="sr-only"> {a.name}</span>
                                        </button>
                                        {mine && (
                                            <button type="button" className={small} onClick={() => onRegenerate(a)}>
                                                New token<span className="sr-only"> for {a.name}</span>
                                            </button>
                                        )}
                                        <button type="button" className={`${small} text-t-bad`} onClick={() => onRevoke(a)}>
                                            Revoke<span className="sr-only"> {a.name}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/** Create and edit share one modal; the level "delete" asks first (WarningConfirm). Expiry is picked at creation only. */
function AgentForm({ agent, projects, onClose }: { agent: AgentRow | 'new' | null; projects: { key: string; name: string }[]; onClose: () => void }) {
    const existing = agent && agent !== 'new' ? agent : null;
    const [allProjects, setAllProjects] = useState(existing ? existing.projects === null : true);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const form = useForm({
        name: existing?.name ?? '',
        description: existing?.description ?? '',
        level: (existing?.level ?? 'write') as Level,
        projects: existing?.projects ?? ([] as string[]),
        expires_in_days: '90',
    });

    const toggleProject = (key: string) =>
        form.setData('projects', form.data.projects.includes(key) ? form.data.projects.filter((k) => k !== key) : [...form.data.projects, key]);

    const send = () => {
        form.transform((d) => ({
            name: d.name,
            description: d.description || null,
            level: d.level,
            projects: allProjects ? null : d.projects,
            ...(existing ? {} : { expires_in_days: d.expires_in_days === '' ? null : Number(d.expires_in_days) }),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };
        existing ? form.patch(route('agents.update', existing.id), options) : form.post(route('agents.store'), options);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Newly giving an agent the delete level is the one step that asks first.
        form.data.level === 'delete' && existing?.level !== 'delete' ? setConfirmDelete(true) : send();
    };

    return (
        <>
            <Modal show={agent !== null} onClose={onClose} maxWidth="lg">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h2 className="text-lg text-text">{existing ? `Edit ${existing.name}` : 'New agent'}</h2>
                    <div>
                        <InputLabel htmlFor="agent-name" value="Name" />
                        <TextInput id="agent-name" className="mt-1 block w-full" placeholder="e.g. Claude Code" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required isFocused />
                        <InputError className="mt-1" message={form.errors.name} />
                    </div>
                    <div>
                        <InputLabel htmlFor="agent-description" value="What it is for (optional)" />
                        <TextInput id="agent-description" className="mt-1 block w-full" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        <InputError className="mt-1" message={form.errors.description} />
                    </div>
                    <fieldset>
                        <legend className="text-sm font-medium text-text">Level</legend>
                        <div className="mt-1 space-y-1">
                            {(Object.keys(levels) as Level[]).map((l) => (
                                <label key={l} className="flex min-h-control-h-sm items-start gap-2 text-sm text-text">
                                    <input type="radio" name="level" className="mt-1" checked={form.data.level === l} onChange={() => form.setData('level', l)} />
                                    <span>
                                        {levels[l].label}
                                        <span className="block text-muted">{levels[l].hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <p className="mt-1 text-sm text-muted">An agent never does more than you can, whatever its level.</p>
                        <InputError className="mt-1" message={form.errors.level} />
                    </fieldset>
                    <fieldset>
                        <legend className="text-sm font-medium text-text">Projects</legend>
                        <label className="mt-1 flex min-h-control-h-sm items-center gap-2 text-sm text-text">
                            <Checkbox checked={allProjects} onChange={(e) => setAllProjects(e.target.checked)} />
                            All my projects (including ones I join later)
                        </label>
                        {!allProjects && (
                            <div className="mt-1 flex flex-wrap gap-x-4 text-sm text-text">
                                {projects.map((p) => (
                                    <label key={p.key} className="flex min-h-control-h-sm items-center gap-2">
                                        <Checkbox checked={form.data.projects.includes(p.key)} onChange={() => toggleProject(p.key)} />
                                        <span className="font-mono">{p.key}</span> {p.name}
                                    </label>
                                ))}
                            </div>
                        )}
                        <InputError className="mt-1" message={form.errors.projects} />
                    </fieldset>
                    {!existing && (
                        <div>
                            <InputLabel htmlFor="agent-expiry" value="Token expires" />
                            <select id="agent-expiry" className={`${selectClass} mt-1`} value={form.data.expires_in_days} onChange={(e) => form.setData('expires_in_days', e.target.value)}>
                                <option value="30">In 30 days</option>
                                <option value="90">In 90 days</option>
                                <option value="365">In 1 year</option>
                                <option value="">Never</option>
                            </select>
                            <InputError className="mt-1" message={form.errors.expires_in_days} />
                        </div>
                    )}
                    <div className="flex justify-end gap-2">
                        <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                        <PrimaryButton disabled={form.processing || (!allProjects && form.data.projects.length === 0)}>{existing ? 'Save changes' : 'Create agent'}</PrimaryButton>
                    </div>
                </form>
            </Modal>
            <WarningConfirm
                open={confirmDelete}
                title="Let this agent delete things?"
                warnings={[
                    { text: 'It can delete issues, comments, labels and columns wherever your role allows.', reason: 'Deleted issues and comments can be restored; other deletes can’t.' },
                    { text: 'Delete projects stays yours alone to do in the web app, unless you are its Admin.' },
                ]}
                confirmLabel="Allow delete"
                processing={form.processing}
                onCancel={() => setConfirmDelete(false)}
                onConfirm={() => {
                    setConfirmDelete(false);
                    send();
                }}
            />
        </>
    );
}
