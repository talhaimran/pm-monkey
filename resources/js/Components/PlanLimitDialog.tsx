import { DialogTitle } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Modal from './Modal';
import SecondaryButton from './SecondaryButton';

export type PlanLimit = { error: 'plan_limit'; limit: string; allowed: number; current: number; message: string; upgrade_url: string | null };

const titles: Record<string, string> = { billed_users: 'Billed user limit reached', viewers: 'Viewer limit reached', projects: 'Project limit reached' };

/**
 * Design system plan-limit dialog (spec 7.0c): shown after the server refused an action, nothing was changed.
 * Owners get "Upgrade plan" when the install has a billing link; everyone else is told to ask an Owner.
 */
export default function PlanLimitDialog() {
    const { flash, workspace } = usePage().props as { flash?: { planLimit?: PlanLimit | null }; workspace?: { role: string | null } | null };
    const [shown, setShown] = useState<PlanLimit | null>(null);
    const close = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (flash?.planLimit) setShown(flash.planLimit);
    }, [flash?.planLimit]);

    const owner = workspace?.role === 'owner';
    const seats = shown?.limit === 'billed_users' || shown?.limit === 'viewers';

    return (
        <Modal show={shown !== null} onClose={() => setShown(null)} maxWidth="md" initialFocus={close}>
            {shown && (
                <div className="space-y-4 p-6">
                    <div className="flex items-start gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-tile tint-warn">
                            <Gauge className="h-5 w-5" aria-hidden="true" />
                        </span>
                        <DialogTitle className="pt-1.5 text-heading-2xl text-text">{titles[shown.limit] ?? 'Plan limit reached'}</DialogTitle>
                    </div>
                    <p className="text-md text-text">{shown.message}</p>
                    <div>
                        <div
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={shown.allowed}
                            aria-valuenow={shown.current}
                            aria-valuetext={`${shown.current} of ${shown.allowed} used`}
                            className="h-2 overflow-hidden rounded-pill bg-card-2"
                        >
                            <div className="h-full w-full rounded-pill bg-c-warn" />
                        </div>
                        <p className="mt-1 text-sm text-muted">
                            {shown.current} of {shown.allowed} used
                        </p>
                    </div>
                    {!owner && <p className="text-sm text-muted">Ask an Owner to upgrade.</p>}
                    <div className="flex flex-wrap justify-end gap-2">
                        {owner && seats && (
                            <Link href={route('settings.members')} onClick={() => setShown(null)} className="flex h-control-h items-center rounded-ctl px-3 text-md text-text hover:bg-card-2">
                                Manage members
                            </Link>
                        )}
                        <SecondaryButton ref={close} onClick={() => setShown(null)}>
                            Close
                        </SecondaryButton>
                        {owner && shown.upgrade_url && (
                            <a href={shown.upgrade_url} target="_blank" rel="noopener noreferrer" className="flex h-control-h items-center rounded-ctl bg-primary px-3 text-md text-on-primary hover:opacity-90">
                                Upgrade plan
                            </a>
                        )}
                    </div>
                </div>
            )}
        </Modal>
    );
}
