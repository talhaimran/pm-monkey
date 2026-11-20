import Avatar from '@/Components/Avatar';
import EmptyState from '@/Components/EmptyState';
import InputLabel from '@/Components/InputLabel';
import { selectClass } from '@/Components/IssueFormModal';
import Pagination, { Paginated } from '@/Components/Pagination';
import TextInput from '@/Components/TextInput';
import WorkspaceSettingsHeader from '@/Components/WorkspaceSettingsHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Download, ScrollText } from 'lucide-react';

type Entry = {
    id: number;
    created_at: string;
    actor: string | null;
    actor_type: 'user' | 'system' | 'staff';
    via: string;
    action: string;
    target_label: string | null;
    metadata: Record<string, unknown> | null;
    ip: string | null;
};
type Filters = { actor?: string; group?: string; from?: string; to?: string };

/** Plain sentences for each action (design system AuditLogTable); unknown actions show as they are. */
const sentences: Record<string, string> = {
    'auth.login': 'Signed in',
    'auth.login_failed': 'Failed to sign in',
    'auth.logout': 'Signed out',
    'auth.password_changed': 'Changed their password',
    'auth.password_reset': 'Reset their password',
    'workspace.created': 'Created the workspace',
    'workspace.updated': 'Renamed the workspace',
    'workspace.settings_changed': 'Changed workspace settings',
    'workspace.deletion_scheduled': 'Scheduled the workspace for deletion',
    'workspace.deletion_cancelled': 'Cancelled the workspace deletion',
    'member.invited': 'Invited',
    'member.invitation_revoked': 'Revoked the invitation for',
    'member.joined': 'Joined',
    'member.role_changed': 'Changed the role of',
    'owner.added': 'Made an Owner:',
    'owner.removed': 'Removed as Owner:',
    'member.removed': 'Removed',
    'project.created': 'Created project',
    'project.deleted': 'Deleted project',
    'project.admin_joined': 'Joined as Admin:',
    'token.created': 'Created API token',
    'token.revoked': 'Revoked API token',
    'agent.created': 'Created agent',
    'agent.updated': 'Changed agent',
    'agent.paused': 'Paused agent',
    'agent.resumed': 'Resumed agent',
    'agent.revoked': 'Revoked agent',
    'agent.token_regenerated': 'Regenerated the token of agent',
    'issue.purged': 'Purged deleted issue',
    'audit_log.exported': 'Exported the audit log',
};
const viaLabel: Record<string, string> = { web: 'Web', api: 'API', mcp: 'MCP', system: 'System' };

/** "member → admin", or "name: Acme → Acme Co" for before/after changes. */
function detail(m: Entry['metadata']): string | null {
    if (!m) return null;
    if (m.from && m.to) return `${m.from} → ${m.to}`;
    if (m.before && m.after) {
        const before = m.before as Record<string, unknown>;
        const after = m.after as Record<string, unknown>;
        return Object.keys(after)
            .map((k) => `${k.replaceAll('_', ' ')}: ${String(before[k])} → ${String(after[k])}`)
            .join('; ');
    }
    return null;
}

export default function AuditLog({
    entries,
    filters,
    actors,
    groups,
}: {
    entries: Paginated<Entry>;
    filters: Filters;
    actors: { id: number; name: string }[];
    groups: string[];
}) {
    const filtered = Object.values(filters).some(Boolean);
    const apply = (change: Filters) =>
        router.get(route('settings.audit-log'), Object.fromEntries(Object.entries({ ...filters, ...change }).filter(([, v]) => v)), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    return (
        <AuthenticatedLayout header={<WorkspaceSettingsHeader />}>
            <Head title="Audit log" />

            <div className="space-y-4 px-4 pb-10 md:px-8">
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <InputLabel htmlFor="actor" value="Who" />
                        <select id="actor" value={filters.actor ?? ''} className={`${selectClass} mt-1 w-44`} onChange={(e) => apply({ actor: e.target.value })}>
                            <option value="">Anyone</option>
                            {actors.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="group" value="Action" />
                        <select id="group" value={filters.group ?? ''} className={`${selectClass} mt-1 w-40`} onChange={(e) => apply({ group: e.target.value })}>
                            <option value="">All actions</option>
                            {[...new Set([...groups, ...(filters.group ? [filters.group] : [])])].map((g) => (
                                <option key={g} value={g}>
                                    {g.replace('_', ' ')}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="from" value="From" />
                        <TextInput id="from" type="date" value={filters.from ?? ''} className="mt-1 w-40" onChange={(e) => apply({ from: e.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="to" value="To" />
                        <TextInput id="to" type="date" value={filters.to ?? ''} className="mt-1 w-40" onChange={(e) => apply({ to: e.target.value })} />
                    </div>
                    {/* A plain link: the CSV is a download, not an Inertia visit. */}
                    <a
                        href={route('settings.audit-log.export', filters)}
                        className="ms-auto flex h-control-h items-center gap-2 rounded-ctl border border-line-2 bg-card px-3 text-md text-text hover:bg-card-2"
                    >
                        <Download className="h-4 w-4" aria-hidden="true" />
                        Export CSV
                    </a>
                </div>

                <section className="overflow-x-auto rounded-card bg-card shadow">
                    {entries.data.length === 0 ? (
                        <div className="p-5">
                            {filtered ? (
                                <EmptyState
                                    icon={ScrollText}
                                    title="Nothing matches these filters."
                                    action={{ label: 'Clear filters', onClick: () => router.get(route('settings.audit-log')) }}
                                />
                            ) : (
                                <EmptyState icon={ScrollText} title="Nothing logged here yet." hint="Sign-ins, member and project changes appear here as they happen." />
                            )}
                        </div>
                    ) : (
                        <table className="min-w-full text-left text-md">
                            <thead className="border-b border-line text-xs text-muted">
                                <tr>
                                    {['When', 'Who', 'Action', 'Via', 'IP'].map((h) => (
                                        <th key={h} scope="col" className="px-4 py-2 font-normal">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {entries.data.map((e) => {
                                    const when = new Date(e.created_at);
                                    const extra = detail(e.metadata);
                                    return (
                                        <tr key={e.id} className="align-top">
                                            <td className="whitespace-nowrap px-4 py-2 text-muted">
                                                <time dateTime={e.created_at} title={when.toLocaleString()}>
                                                    {when.toLocaleDateString()} {when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </time>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-2 text-text">
                                                <span className="flex items-center gap-2">
                                                    {e.actor && <Avatar name={e.actor} email={e.actor} size="xs" />}
                                                    {e.actor ?? (e.actor_type === 'system' ? 'System' : 'Unknown')}
                                                </span>
                                            </td>
                                            <td className="min-w-[16rem] px-4 py-2 text-text">
                                                {sentences[e.action] ?? e.action} {e.target_label && <strong className="font-medium">{e.target_label}</strong>}
                                                {extra && <span className="block text-sm text-muted">{extra}</span>}
                                            </td>
                                            <td className="px-4 py-2">
                                                <span className="inline-flex h-pill-h items-center rounded-pill tint-slate px-2.5 text-xs">{viaLabel[e.via] ?? e.via}</span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-2 text-sm text-muted">{e.ip ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </section>
                {entries.data.length > 0 && <Pagination page={entries} />}
            </div>
        </AuthenticatedLayout>
    );
}
