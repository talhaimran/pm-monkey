// Toast state for the Undo pattern (spec 5.1, design system Toast). Pure functions, so they run under `node --test`.

/** What the server flashes after a reversible change: the request that reverses it (see App\Services\UndoService). */
export type UndoFlash = { message: string; method: 'patch' | 'post'; url: string; data: Record<string, unknown> };

export type Toast = { id: number; message: string; tone: 'plain' | 'good' | 'bad'; undo?: UndoFlash };

const MAX_STACKED = 3;
let nextId = 1;

/** Add a toast, keeping the newest three. */
export function push(toasts: Toast[], toast: Omit<Toast, 'id'>): Toast[] {
    return [...toasts, { ...toast, id: nextId++ }].slice(-MAX_STACKED);
}

/** How long a toast shows, in ms: 6s with Undo, 4.2s without. */
export function lifetime(toast: Toast): number {
    return toast.undo ? 6000 : 4200;
}

/** The toast ⌘Z / Ctrl+Z acts on: the newest one that offers Undo. */
export function undoTarget(toasts: Toast[]): Toast | undefined {
    return toasts.findLast((t) => t.undo);
}

/** True while focus is in a field, where ⌘Z and single-key shortcuts must stay with the browser. */
export function isTyping(target: unknown): boolean {
    const el = target as { tagName?: string; isContentEditable?: boolean } | null;
    return !!el && (el.isContentEditable === true || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName ?? ''));
}
