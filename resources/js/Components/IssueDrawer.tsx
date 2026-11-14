import IssueView, { IssueViewData } from '@/Components/IssueView';
import { issueUrl, peek } from '@/issueUrl';
import { transition } from '@/motion';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { Check, Link as LinkIcon, Maximize2, X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useState } from 'react';

const iconButton = 'flex h-control-h-sm w-control-h-sm items-center justify-center rounded-sm text-muted hover:bg-card-2 hover:text-text';

/**
 * The peek drawer: the issue named by ?issue={KEY}, over the board, list or search, so people keep their place.
 * Headless UI Dialog (focus trap, Esc, scrim, focus back to the card). 500px from the right; a bottom sheet under 760px.
 */
export default function IssueDrawer() {
    const data = (usePage().props.peek ?? null) as IssueViewData | null;
    const [copied, setCopied] = useState(false);
    const sheet = typeof window !== 'undefined' && window.matchMedia('(max-width: 759px)').matches;
    const hidden = sheet ? { y: 90, opacity: 0 } : { x: 70, opacity: 0 };

    const copy = (key: string) =>
        navigator.clipboard.writeText(issueUrl(key)).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });

    return (
        <AnimatePresence>
            {data && (
                <Dialog static open onClose={() => peek(null)} className="fixed inset-0 z-40">
                    <motion.div
                        aria-hidden="true"
                        className="scrim fixed inset-0"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1, transition: transition('drawerIn') }}
                        exit={{ opacity: 0, transition: transition('drawerOut') }}
                    />
                    <DialogPanel
                        as={motion.div}
                        initial={hidden}
                        animate={{ x: 0, y: 0, opacity: 1, transition: transition('drawerIn') }}
                        exit={{ ...hidden, transition: transition('drawerOut') }}
                        className="fixed inset-x-0 bottom-0 flex max-h-[90vh] flex-col rounded-t-sheet bg-card shadow-pop md:inset-x-auto md:inset-y-3 md:end-3 md:max-h-none md:w-drawer-w md:max-w-[calc(100vw-1.5rem)] md:rounded-sheet"
                    >
                        <div className="flex items-center gap-1 border-b border-line px-5 py-3">
                            <span className="me-auto text-2xs text-muted">
                                {data.issue.key} · {data.issue.type}
                                {data.issue.sprint && ` · ${data.issue.sprint.name}`}
                            </span>
                            <Link href={issueUrl(data.issue.key)} className={iconButton} aria-label="Open full page" title="Open full page">
                                <Maximize2 className="h-4 w-4" aria-hidden="true" />
                            </Link>
                            <button type="button" onClick={() => copy(data.issue.key)} className={iconButton} aria-label={copied ? 'Link copied' : 'Copy link'} title="Copy link">
                                {copied ? <Check className="h-4 w-4 text-t-good" aria-hidden="true" /> : <LinkIcon className="h-4 w-4" aria-hidden="true" />}
                            </button>
                            <button type="button" onClick={() => peek(null)} className={iconButton} aria-label="Close">
                                <X className="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                        <div className="overflow-y-auto px-5 py-5">
                            <IssueView key={data.issue.key} {...data} heading={DialogTitle} />
                        </div>
                    </DialogPanel>
                </Dialog>
            )}
        </AnimatePresence>
    );
}
