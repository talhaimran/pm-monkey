import Avatar from '@/Components/Avatar';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { selectClass } from '@/Components/IssueFormModal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import WarningConfirm from '@/Components/WarningConfirm';
import WorkspaceSettingsHeader from '@/Components/WorkspaceSettingsHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ProjectRole, WorkspaceRole } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MailPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Member = {
    id: number;
    name: string;
    email: string;
    role: WorkspaceRole;
    billed: boolean;
    projects: { key: string; role: ProjectRole }[];
    last_active_at: string | null;
    two_factor: boolean;
};
type PendingInvitation = {
    id: number;
    email: string;
    role: WorkspaceRole;
    project: string | null;
    project_role: ProjectRole | null;
    invited_by: string | null;
    expires_at: string;
};

const card = 'rounded-card bg-card p-5 shadow sm:p-6';
const pill = 'inline-flex h-pill-h items-center rounded-pill px-2.5 text-xs';
const roleLabel = { owner: 'Owner', admin: 'Admin', member: 'Member' } as const;
const date = (iso: string) => new Date(iso).toLocaleDateString();

export default function Members({
    members,
    invitations,
    projects,
    soleAdminOf,
    can,
}: {
    members: Member[];
    invitations: PendingInvitation[];
    projects: string[];
    soleAdminOf: Record<number, string[]>;
    can: { manageOwners: boolean };
}) {
    const me = usePage().props.auth.user;
    const errors = usePage().props.errors as Record<string, string>;
    const invite = useForm({ email: '', role: 'member', project: '', project_role: 'member' });
    const [removing, setRemoving] = useState<Member | null>(null);
    const [newAdmins, setNewAdmins] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const sendInvite: FormEventHandler = (e) => {
        e.preventDefault();
        invite.post(route('settings.invitations.store'), { preserveScroll: true, onSuccess: () => invite.reset('email', 'project') });
    };
    const changeRole = (m: Member, role: string) => router.patch(route('settings.members.update', m.id), { role }, { preserveScroll: true });
    const remove = () =>
        removing &&
        router.delete(route('settings.members.destroy', removing.id), {
            data: { new_admins: newAdmins },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setRemoving(null),
        });
    const roleOptions = (m: Member): WorkspaceRole[] => (can.manageOwners ? ['owner', 'admin', 'member'] : m.role === 'owner' ? ['owner'] : ['admin', 'member']);
    const handover = removing ? (soleAdminOf[removing.id] ?? []) : [];

    return (
        <AuthenticatedLayout header={<WorkspaceSettingsHeader />}>
            <Head title="Members" />

            <div className="max-w-5xl space-y-6 px-4 pb-10 md:px-8">
                <section className={card}>
                    <h2 className="text-lg text-text">Invite people</h2>
                    <form onSubmit={sendInvite} className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto] lg:items-end">
                        <div>
                            <InputLabel htmlFor="invite-email" value="Email" />
                            <TextInput id="invite-email" type="email" value={invite.data.email} className="mt-1 block w-full" onChange={(e) => invite.setData('email', e.target.value)} required />
                        </div>
                        <div>
                            <InputLabel htmlFor="invite-role" value="Workspace role" />
                            <select id="invite-role" value={invite.data.role} className={`${selectClass} mt-1 block w-full`} onChange={(e) => invite.setData('role', e.target.value)}>
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="invite-project" value="Project (optional)" />
                            <select id="invite-project" value={invite.data.project} className={`${selectClass} mt-1 block w-full`} onChange={(e) => invite.setData('project', e.target.value)}>
                                <option value="">None</option>
                                {projects.map((key) => (
                                    <option key={key}>{key}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="invite-project-role" value="Project role" />
                            <select
                                id="invite-project-role"
                                value={invite.data.project_role}
                                disabled={!invite.data.project}
                                className={`${selectClass} mt-1 block w-full disabled:opacity-60`}
                                onChange={(e) => invite.setData('project_role', e.target.value)}
                            >
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </div>
                        <PrimaryButton disabled={invite.processing} className="justify-center">
                            Send invite
                        </PrimaryButton>
                    </form>
                    {['email', 'role', 'project', 'project_role'].map((f) => (
                        <InputError key={f} message={invite.errors[f as keyof typeof invite.errors]} className="mt-2" />
                    ))}
                </section>

                <section className={card}>
                    <h2 className="text-lg text-text">
                        Members <span className="text-muted">· {members.length}</span>
                    </h2>
                    <InputError message={errors.role ?? errors.member} className="mt-2" />
                    <ul className="mt-3 divide-y divide-line">
                        {members.map((m) => (
                            <li key={m.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                                <div className="flex min-w-0 flex-1 basis-56 items-center gap-3">
                                    <Avatar name={m.name} email={m.email} size="sm" />
                                    <div className="min-w-0">
                                        <div className="truncate text-md text-text">
                                            {m.name}
                                            {m.id === me.id && <span className="text-muted"> (you)</span>}
                                        </div>
                                        <div className="truncate text-sm text-muted">{m.email}</div>
                                    </div>
                                </div>
                                <span className="w-16">
                                    <span className={`${pill} ${m.billed ? 'tint-cobalt' : 'tint-slate'}`}>{m.billed ? 'Billed' : 'Viewer'}</span>
                                </span>
                                <span className="w-16">
                                    <span className={`${pill} ${m.two_factor ? 'tint-good' : 'tint-slate'}`}>{m.two_factor ? '2FA' : 'No 2FA'}</span>
                                </span>
                                <div className="w-40 truncate text-sm text-muted" title={m.projects.map((p) => `${p.key} (${p.role})`).join(', ')}>
                                    {m.projects.length ? m.projects.map((p) => p.key).join(', ') : 'No projects'}
                                </div>
                                <div className="w-28 text-sm text-muted">{m.last_active_at ? `Active ${date(m.last_active_at)}` : 'Not active yet'}</div>
                                <select
                                    aria-label={`Role of ${m.name}`}
                                    value={m.role}
                                    disabled={m.role === 'owner' && !can.manageOwners}
                                    className={`${selectClass} w-28 disabled:opacity-60`}
                                    onChange={(e) => changeRole(m, e.target.value)}
                                >
                                    {roleOptions(m).map((r) => (
                                        <option key={r} value={r}>
                                            {roleLabel[r]}
                                        </option>
                                    ))}
                                </select>
                                <SecondaryButton
                                    className="w-24 justify-center"
                                    disabled={m.role === 'owner' && !can.manageOwners}
                                    onClick={() => {
                                        setNewAdmins({});
                                        setRemoving(m);
                                    }}
                                >
                                    {m.id === me.id ? 'Leave' : 'Remove'}
                                </SecondaryButton>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className={card}>
                    <h2 className="text-lg text-text">Pending invitations</h2>
                    {invitations.length === 0 ? (
                        <div className="mt-3">
                            <EmptyState icon={MailPlus} title="No pending invitations." hint="Invite someone with the form above." />
                        </div>
                    ) : (
                        <ul className="mt-3 divide-y divide-line">
                            {invitations.map((i) => (
                                <li key={i.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 text-sm">
                                    <div className="min-w-0 flex-1 basis-56">
                                        <div className="truncate text-md text-text">{i.email}</div>
                                        <div className="text-muted">
                                            {roleLabel[i.role]}
                                            {i.project && ` · ${i.project} ${i.project_role}`}
                                            {i.invited_by && ` · invited by ${i.invited_by}`}
                                        </div>
                                    </div>
                                    <span className="text-muted">Expires {date(i.expires_at)}</span>
                                    <SecondaryButton onClick={() => router.post(route('settings.invitations.resend', i.id), {}, { preserveScroll: true })}>Resend</SecondaryButton>
                                    <SecondaryButton onClick={() => router.delete(route('settings.invitations.destroy', i.id), { preserveScroll: true })}>Revoke</SecondaryButton>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <WarningConfirm
                open={removing !== null}
                title={removing?.id === me.id ? 'Leave this workspace?' : `Remove ${removing?.name ?? ''}?`}
                warnings={[
                    { text: 'They leave every project in this workspace.', reason: 'Their issues and comments stay.' },
                    { text: 'Their agents in this workspace are revoked.' },
                ]}
                confirmLabel={removing?.id === me.id ? 'Leave workspace' : 'Remove member'}
                processing={processing}
                onCancel={() => setRemoving(null)}
                onConfirm={remove}
            >
                {handover.length > 0 && (
                    <div className="space-y-3">
                        <p className="text-sm text-text">Pick a new admin for each project they are the only admin of.</p>
                        {handover.map((key) => (
                            <div key={key}>
                                <InputLabel htmlFor={`new-admin-${key}`} value={`New admin of ${key}`} />
                                <select
                                    id={`new-admin-${key}`}
                                    required
                                    value={newAdmins[key] ?? ''}
                                    className={`${selectClass} mt-1 block w-full`}
                                    onChange={(e) => setNewAdmins((a) => ({ ...a, [key]: e.target.value }))}
                                >
                                    <option value="" disabled>
                                        Choose a member
                                    </option>
                                    {members
                                        .filter((m) => m.id !== removing?.id)
                                        .map((m) => (
                                            <option key={m.id} value={m.email}>
                                                {m.name} ({m.email})
                                            </option>
                                        ))}
                                </select>
                                <InputError message={errors[`new_admins.${key}`]} className="mt-1" />
                            </div>
                        ))}
                        <InputError message={errors.new_admins} />
                    </div>
                )}
            </WarningConfirm>
        </AuthenticatedLayout>
    );
}
