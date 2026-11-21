import AgentLabel from '@/Components/AgentLabel';
import { selectClass } from '@/Components/IssueFormModal';
import StatusLozenge from '@/Components/StatusLozenge';
import { issueUrl } from '@/issueUrl';
import { ApprovalRequest } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';

const button = 'inline-flex h-control-h-sm items-center justify-center rounded-ctl px-3 text-sm transition-colors duration-instant disabled:cursor-not-allowed disabled:opacity-50';

/** "Expires in 6 days" / "Expires in 5 hours", from the request's expiry. */
function expiresIn(at: string): string {
    const hours = Math.max(0, Math.ceil((new Date(at).getTime() - Date.now()) / 3_600_000));
    if (hours <= 0) return 'Expires soon';
    if (hours < 24) return `Expires in ${hours} ${hours === 1 ? 'hour' : 'hours'}`;
    const days = Math.ceil(hours / 24);
    return `Expires in ${days} ${days === 1 ? 'day' : 'days'}`;
}

/**
 * Design system approval card (A2): an agent's move to Done waiting for a person. Sky tile when pending; butter
 * (`is-stale`) when the issue moved since, so approving confirms the change; read-only once expired or stale.
 * `linkIssue` names the issue as a link (dashboard); `columns` colours the status pills.
 */
export default function ApprovalCard({ approval: a, canDecide, linkIssue = false, columns }: { approval: ApprovalRequest; canDecide: boolean; linkIssue?: boolean; columns?: string[] }) {
    const [busy, setBusy] = useState(false);
    const [noting, setNoting] = useState(false);
    const [note, setNote] = useState('');
    const pending = a.status === 'pending';
    const pills = columns ?? [a.from, a.to];
    const send = (action: 'approve' | 'reject', data: Record<string, unknown>) =>
        router.post(route(`approvals.${action}`, a.id), data as never, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    return (
        <article className={`grid gap-2.5 rounded-tile px-3.5 py-3 text-on-tile ${pending && a.moved ? 'bg-tile-butter' : 'bg-tile-sky'}`} aria-label={`${a.agent.name} wants to move ${a.issue} to ${a.to}`}>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-semibold">
                <AgentLabel agent={a.agent} />
                <span>
                    wants to move{' '}
                    {linkIssue ? (
                        <Link href={issueUrl(a.issue)} className="underline hover:no-underline">
                            {a.issue}
                        </Link>
                    ) : (
                        a.issue
                    )}{' '}
                    to {a.to}
                </span>
                <small className="ms-auto text-xs font-semibold text-muted">{pending ? expiresIn(a.expires_at) : a.status === 'expired' ? 'Expired' : 'Stale'}</small>
            </div>
            {linkIssue && <p className="truncate text-sm">{a.title}</p>}
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <StatusLozenge status={a.from} columns={pills} />
                <ArrowRight className="h-4 w-4" aria-label="to" />
                <StatusLozenge status={a.to} columns={pills} />
            </div>
            {a.reason && <p className="text-sm">{a.reason}</p>}
            {pending && a.moved && (
                <p className="text-sm font-semibold">
                    {a.issue} moved from {a.from} to {a.now} since this request. Review before approving.
                </p>
            )}
            {pending && canDecide && (
                <>
                    {noting && (
                        <input
                            aria-label="Note for the agent (optional)"
                            placeholder="Note for the agent (optional)"
                            maxLength={500}
                            className={`${selectClass} w-full`}
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    )}
                    <div className="flex flex-wrap items-center gap-2">
                        <button type="button" disabled={busy} className={`${button} bg-primary text-on-primary hover:opacity-90`} onClick={() => send('approve', a.moved ? { confirm_changed: true } : {})}>
                            Approve move to {a.to}
                        </button>
                        <button type="button" disabled={busy} className={`${button} text-text hover:bg-card`} onClick={() => send('reject', note.trim() ? { note: note.trim() } : {})}>
                            Reject
                        </button>
                        {!noting && (
                            <button type="button" className={`${button} ms-auto text-muted hover:bg-card hover:text-text`} onClick={() => setNoting(true)}>
                                Add a note
                            </button>
                        )}
                    </div>
                </>
            )}
        </article>
    );
}
