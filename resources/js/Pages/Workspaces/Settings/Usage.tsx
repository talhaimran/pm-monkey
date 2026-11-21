import WorkspaceSettingsHeader from '@/Components/WorkspaceSettingsHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Limit = { key: string; label: string; current: number; allowed: number | null };

const card = 'rounded-card bg-card p-5 shadow sm:p-6';

/** Spec 7.0c: usage against each limit enforced so far. No limit set means unlimited. */
export default function Usage({ workspaceLimits, tokenLimits }: { workspaceLimits: Limit[] | null; tokenLimits: { per_minute: number; per_day: number | null } }) {
    return (
        <AuthenticatedLayout header={<WorkspaceSettingsHeader />}>
            <Head title="Usage and limits" />

            <div className="max-w-3xl space-y-6 px-4 pb-10 md:px-8">
                {workspaceLimits && (
                    <section className={card}>
                        <h2 className="text-lg text-text">Workspace</h2>
                        <p className="mt-1 text-sm text-muted">Pending invitations count as the people they would add.</p>
                        <ul className="mt-4 space-y-4">
                            {workspaceLimits.map((l) => {
                                const full = l.allowed !== null && l.current >= l.allowed;
                                return (
                                    <li key={l.key}>
                                        <div className="flex items-baseline justify-between gap-3 text-md">
                                            <span className="text-text">{l.label}</span>
                                            <span className={full ? 'text-t-warn' : 'text-muted'}>
                                                {l.allowed === null ? `${l.current} · Unlimited` : `${l.current} of ${l.allowed}`}
                                            </span>
                                        </div>
                                        {l.allowed !== null && (
                                            <div
                                                role="progressbar"
                                                aria-label={l.label}
                                                aria-valuemin={0}
                                                aria-valuemax={l.allowed}
                                                aria-valuenow={Math.min(l.current, l.allowed)}
                                                aria-valuetext={`${l.current} of ${l.allowed} used`}
                                                className="mt-1.5 h-2 overflow-hidden rounded-pill bg-card-2"
                                            >
                                                <div
                                                    className={`h-full rounded-pill ${full ? 'bg-c-warn' : 'bg-primary'}`}
                                                    style={{ width: `${l.allowed === 0 ? 100 : Math.min(100, (l.current / l.allowed) * 100)}%` }}
                                                />
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                )}

                <section className={card}>
                    <h2 className="text-lg text-text">Your agents</h2>
                    <p className="mt-1 text-sm text-muted">Each agent token gets its own budget, for the REST API and MCP.</p>
                    <dl className="mt-4 grid grid-cols-2 gap-4 text-md">
                        <div>
                            <dt className="text-muted">Per minute</dt>
                            <dd className="text-text">{tokenLimits.per_minute.toLocaleString()} requests</dd>
                        </div>
                        <div>
                            <dt className="text-muted">Per day</dt>
                            <dd className="text-text">{tokenLimits.per_day === null ? 'Unlimited' : `${tokenLimits.per_day.toLocaleString()} requests`}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
