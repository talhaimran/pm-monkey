import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import WorkspaceSettingsHeader from '@/Components/WorkspaceSettingsHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** Spec 7.0e and 7.0f: require two-factor, and choose which sign-in methods may enter the workspace. */
const methods = [
    ['password', 'Password'],
    ['google', 'Google'],
    ['github', 'GitHub'],
] as const;

export default function Security({
    require2fa,
    agentsEnabled,
    members,
    membersWithout2fa,
    signInMethods,
    configured,
}: {
    require2fa: boolean;
    agentsEnabled: boolean;
    members: number;
    membersWithout2fa: number;
    signInMethods: string[];
    configured: string[];
}) {
    const form = useForm({ require_2fa: require2fa });
    const agents = useForm({ agents_enabled: agentsEnabled });
    const signIn = useForm({ sign_in_methods: signInMethods });
    const toggle = (m: string, on: boolean) =>
        signIn.setData('sign_in_methods', on ? [...signIn.data.sign_in_methods, m] : signIn.data.sign_in_methods.filter((x) => x !== m));
    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('settings.security.update'), { preserveScroll: true });
    };
    const turningOn = form.data.require_2fa && !require2fa;

    return (
        <AuthenticatedLayout header={<WorkspaceSettingsHeader />}>
            <Head title="Security" />

            <div className="max-w-3xl space-y-6 px-4 pb-10 md:px-8">
                <section className="rounded-card bg-card p-5 shadow sm:p-6">
                    <h2 className="text-lg text-text">Two-factor authentication</h2>
                    <p className="mt-1 text-sm text-muted">
                        {membersWithout2fa === 0
                            ? `All ${members} members have two-factor on.`
                            : `${membersWithout2fa} of ${members} members don’t have two-factor on yet.`}{' '}
                        <Link href={route('settings.members')} className="underline hover:text-text">
                            See who
                        </Link>
                    </p>
                    <form onSubmit={save} className="mt-4 space-y-4">
                        <label className="flex min-h-control-h items-center gap-3">
                            <Checkbox checked={form.data.require_2fa} onChange={(e) => form.setData('require_2fa', e.target.checked)} />
                            <span className="text-md text-text">
                                Require two-factor authentication
                                <span className="block text-sm text-muted">Members without it are sent to set it up before they can use this workspace.</span>
                            </span>
                        </label>
                        {turningOn && membersWithout2fa > 0 && (
                            <p role="status" className="rounded-card tint-warn p-3 text-sm">
                                {membersWithout2fa} {membersWithout2fa === 1 ? 'member' : 'members'} will have to set it up on their next visit.
                            </p>
                        )}
                        <p className="text-sm text-muted">Agents that already exist keep working. Creating a new agent will need two-factor on.</p>
                        <InputError message={form.errors.require_2fa} />
                        <div className="flex justify-end">
                            <PrimaryButton disabled={form.processing || !form.isDirty}>Save changes</PrimaryButton>
                        </div>
                    </form>
                </section>

                <section className="rounded-card bg-card p-5 shadow sm:p-6">
                    <h2 className="text-lg text-text">Agents</h2>
                    <p className="mt-1 text-sm text-muted">Agents are Claude Code, Codex or scripts that work here as their owner, through the API or MCP.</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            agents.patch(route('settings.agent-policy.update'), { preserveScroll: true });
                        }}
                        className="mt-4 space-y-4"
                    >
                        <label className="flex min-h-control-h items-center gap-3">
                            <Checkbox checked={agents.data.agents_enabled} onChange={(e) => agents.setData('agents_enabled', e.target.checked)} />
                            <span className="text-md text-text">
                                Allow agents in this workspace
                                <span className="block text-sm text-muted">Turned off, every agent call is refused with “agents_disabled”. People are not affected, and agents keep their settings.</span>
                            </span>
                        </label>
                        <InputError message={agents.errors.agents_enabled} />
                        <div className="flex justify-end">
                            <PrimaryButton disabled={agents.processing || !agents.isDirty}>Save changes</PrimaryButton>
                        </div>
                    </form>
                </section>

                <section className="rounded-card bg-card p-5 shadow sm:p-6">
                    <h2 className="text-lg text-text">Sign-in methods</h2>
                    <p className="mt-1 text-sm text-muted">People signed in another way can’t open this workspace until they sign in with one of these.</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            signIn.patch(route('settings.sign-in-methods.update'), { preserveScroll: true });
                        }}
                        className="mt-4 space-y-3"
                    >
                        {methods.map(([m, label]) => (
                            <label key={m} className="flex min-h-control-h items-center gap-3">
                                <Checkbox checked={signIn.data.sign_in_methods.includes(m)} onChange={(e) => toggle(m, e.target.checked)} />
                                <span className="text-md text-text">
                                    {label}
                                    {m !== 'password' && !configured.includes(m) && <span className="block text-sm text-muted">Not set up on this install yet.</span>}
                                </span>
                            </label>
                        ))}
                        <InputError message={signIn.errors.sign_in_methods} />
                        <div className="flex justify-end">
                            <PrimaryButton disabled={signIn.processing || !signIn.isDirty}>Save changes</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
