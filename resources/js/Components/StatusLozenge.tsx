const styles = {
    todo: 'bg-loz-todo text-loz-todo-ink',
    progress: 'bg-loz-progress text-loz-progress-ink',
    done: 'bg-loz-done text-loz-done-ink',
};

/** Lozenge colours for a status: first column (or unknown columns) gray, last green, others blue. */
export function lozengeClass(status: string, columns?: string[]) {
    const i = columns?.indexOf(status) ?? -1;
    return styles[!columns || i <= 0 ? 'todo' : i === columns.length - 1 ? 'done' : 'progress'];
}

/**
 * Uppercase status pill. With the project's ordered `columns`: first = to do (gray), last = done (green),
 * anything between = in progress (blue). Without columns it stays gray.
 */
export default function StatusLozenge({ status, columns, className = '' }: { status: string; columns?: string[]; className?: string }) {
    return (
        <span
            className={`inline-block max-w-full truncate rounded-[3px] px-1 text-[11px] font-bold uppercase leading-4 ${lozengeClass(status, columns)} ${className}`}
        >
            {status}
        </span>
    );
}
