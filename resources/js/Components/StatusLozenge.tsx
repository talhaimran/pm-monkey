const styles = {
    todo: ['tint-sky', 'bg-c-sky'],
    progress: ['tint-amber', 'bg-c-amber'],
    done: ['tint-good', 'bg-c-good'],
};

type Category = keyof typeof styles;

/** Status category from the project's ordered columns: first (or unknown) = to do, last = done, between = in progress. Step 1 replaces this with real categories. */
export function statusCategory(status: string, columns?: string[]): Category {
    const i = columns?.indexOf(status) ?? -1;
    return !columns || i <= 0 ? 'todo' : i === columns.length - 1 ? 'done' : 'progress';
}

/** Tint classes for a status, for chips that aren't a full pill. */
export function lozengeClass(status: string, columns?: string[]) {
    return styles[statusCategory(status, columns)][0];
}

/** Design system Pill: a dot plus the status's own name, coloured by its category. Colour is never the only signal. */
export default function StatusLozenge({ status, columns, className = '' }: { status: string; columns?: string[]; className?: string }) {
    const [tint, dot] = styles[statusCategory(status, columns)];

    return (
        <span className={`inline-flex h-pill-h max-w-full items-center gap-1.5 rounded-pill px-2.5 text-xs ${tint} ${className}`}>
            <span aria-hidden="true" className={`h-1.5 w-1.5 shrink-0 rounded-pill ${dot}`} />
            <span className="truncate">{status}</span>
        </span>
    );
}
