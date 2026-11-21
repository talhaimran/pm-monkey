import { transition } from '@/motion';
import { isTyping, lifetime, push, undoTarget, type Toast, type UndoFlash } from '@/undo';
import { router, usePage } from '@inertiajs/react';
import { Check, CircleAlert, Undo2, X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const TICK = 100;

/**
 * Design system toasts, bottom centre. A reversible change arrives as `flash.undo`; Undo (button, ⌘Z or Ctrl+Z)
 * sends that request back. When the server refuses ("Can't undo: Sam moved WEB-1 to Done.") the toast says so.
 * `role="status"` announces politely; timers pause while the stack is hovered or focused.
 */
export default function Toaster() {
    const { flash, errors } = usePage().props as { flash?: { undo?: UndoFlash | null }; errors?: Record<string, string> };
    const [toasts, setToasts] = useState<Toast[]>([]);
    const left = useRef(new Map<number, number>());
    const paused = useRef(false);

    const add = useCallback((toast: Omit<Toast, 'id'>) => setToasts((current) => push(current, toast)), []);
    const dismiss = useCallback((id: number) => setToasts((current) => current.filter((t) => t.id !== id)), []);

    useEffect(() => {
        if (!flash?.undo) return;
        add({ message: flash.undo.message, tone: 'plain', undo: flash.undo });
        // Take it out of this history entry, or Back would bring the page back with the old toast and a stale Undo.
        router.replace({ props: (props) => ({ ...props, flash: { ...(props.flash as object), undo: null } }), preserveScroll: true, preserveState: true });
    }, [flash?.undo, add]);

    useEffect(() => {
        if (errors?.undo) add({ message: errors.undo, tone: 'bad' });
        // An approval that couldn't go through (moved since, stale, expired, already decided): say why.
        if (errors?.approval) add({ message: errors.approval, tone: 'bad' });
    }, [errors, add]);

    const undo = useCallback(
        (toast: Toast) => {
            if (!toast.undo) return;
            dismiss(toast.id);
            router[toast.undo.method](toast.undo.url, toast.undo.data as Record<string, string>, {
                preserveScroll: true,
                // Validation errors other than `undo` (e.g. the old column no longer exists) also end up in a toast.
                onError: (failed) => !failed.undo && add({ message: Object.values(failed)[0] ?? "Can't undo.", tone: 'bad' }),
            });
        },
        [add, dismiss],
    );

    // One clock for every toast, so pausing is a single flag.
    useEffect(() => {
        if (!toasts.length) return;
        const timer = setInterval(() => {
            if (paused.current) return;
            for (const toast of toasts) {
                const remaining = (left.current.get(toast.id) ?? lifetime(toast)) - TICK;
                left.current.set(toast.id, remaining);
                if (remaining <= 0) dismiss(toast.id);
            }
        }, TICK);
        return () => clearInterval(timer);
    }, [toasts, dismiss]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key.toLowerCase() !== 'z' || !(e.metaKey || e.ctrlKey) || e.shiftKey || isTyping(e.target)) return;
            const target = undoTarget(toasts);
            if (!target) return;
            e.preventDefault();
            undo(target);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [toasts, undo]);

    const pause = (value: boolean) => () => (paused.current = value);

    return (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 bottom-[22px] z-[60] flex flex-col items-center gap-2 px-4"
            onMouseEnter={pause(true)}
            onMouseLeave={pause(false)}
            onFocus={pause(true)}
            onBlur={pause(false)}
        >
            <AnimatePresence>
                {toasts.map((toast) => (
                    <motion.div
                        key={toast.id}
                        layout
                        initial={{ opacity: 0, y: 24 }}
                        animate={{ opacity: 1, y: 0, transition: transition('toast') }}
                        exit={{ opacity: 0, y: 12, transition: transition('dialogOut') }}
                        className="pointer-events-auto flex max-w-full items-center gap-3 rounded-tile bg-ink py-2 pe-2 ps-2 text-on-ink shadow-pop"
                    >
                        <span className={`flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-sm ${toast.tone === 'bad' ? 'bg-c-bad' : 'bg-c-amber'} text-ink`}>
                            {toast.tone === 'bad' ? <CircleAlert className="h-4 w-4" aria-hidden="true" /> : <Check className="h-4 w-4" aria-hidden="true" />}
                        </span>
                        <span className="min-w-0 break-words text-md">{toast.message}</span>
                        {toast.undo && (
                            <button type="button" onClick={() => undo(toast)} className="flex h-control-h-sm items-center gap-1.5 rounded-sm px-2.5 text-md text-c-amber">
                                <Undo2 className="h-4 w-4" aria-hidden="true" />
                                Undo
                            </button>
                        )}
                        <button type="button" onClick={() => dismiss(toast.id)} aria-label="Dismiss" className="flex h-control-h-sm w-control-h-sm items-center justify-center rounded-sm opacity-70 hover:opacity-100">
                            <X className="h-4 w-4" aria-hidden="true" />
                        </button>
                    </motion.div>
                ))}
            </AnimatePresence>
        </div>
    );
}
