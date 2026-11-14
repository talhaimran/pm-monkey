import { DialogTitle } from '@headlessui/react';
import { TriangleAlert } from 'lucide-react';
import { PropsWithChildren, ReactNode, useRef, useState } from 'react';
import Modal from './Modal';
import PrimaryButton from './PrimaryButton';
import SecondaryButton from './SecondaryButton';

/**
 * Design system warning confirm: asks before a change, names it in the title, lists what the person should know,
 * and restates the action on the button. Focus starts on Cancel; Esc cancels. `children` holds extra fields.
 */
export default function WarningConfirm({
    open,
    title,
    warnings,
    confirmLabel,
    onConfirm,
    onCancel,
    processing = false,
    children,
}: PropsWithChildren<{
    open: boolean;
    title: string;
    warnings: { text: string; reason?: string }[];
    confirmLabel: string;
    onConfirm: () => void;
    onCancel: () => void;
    processing?: boolean;
}>) {
    const cancel = useRef<HTMLButtonElement>(null);

    return (
        <Modal show={open} onClose={onCancel} maxWidth="md" initialFocus={cancel}>
            <form
                className="space-y-4 p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    onConfirm();
                }}
            >
                <div className="flex items-start gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-tile tint-warn">
                        <TriangleAlert className="h-5 w-5" aria-hidden="true" />
                    </span>
                    <DialogTitle className="pt-1.5 text-heading-2xl text-text">{title}</DialogTitle>
                </div>
                <ul className="space-y-2">
                    {warnings.map((w) => (
                        <li key={w.text} className="rounded-ctl bg-card-2 px-3 py-2">
                            <span className="text-md text-text">{w.text}</span>
                            {w.reason && <span className="block text-sm text-muted">{w.reason}</span>}
                        </li>
                    ))}
                </ul>
                {children}
                <div className="flex justify-end gap-2">
                    <SecondaryButton ref={cancel} onClick={onCancel}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>{confirmLabel}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

export type Confirmation = { title: string; warnings: { text: string; reason?: string }[]; confirmLabel: string; run: () => void };

/**
 * One warning confirm for a page with several destructive actions: `ask({...})` opens it, `dialog` goes in the page.
 * The last question stays rendered while the dialog animates out.
 */
export function useWarningConfirm(): [(confirmation: Confirmation) => void, ReactNode] {
    const [pending, setPending] = useState<Confirmation | null>(null);
    const [open, setOpen] = useState(false);

    const ask = (confirmation: Confirmation) => {
        setPending(confirmation);
        setOpen(true);
    };
    const dialog = (
        <WarningConfirm
            open={open}
            title={pending?.title ?? ''}
            warnings={pending?.warnings ?? []}
            confirmLabel={pending?.confirmLabel ?? ''}
            onCancel={() => setOpen(false)}
            onConfirm={() => {
                pending?.run();
                setOpen(false);
            }}
        />
    );

    return [ask, dialog];
}
