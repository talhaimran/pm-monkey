const styles = {
    todo: 'bg-loz-todo text-loz-todo-ink',
    progress: 'bg-loz-progress text-loz-progress-ink',
    done: 'bg-loz-done text-loz-done-ink',
};

/**
 * Uppercase status pill. With the project's ordered `columns`: first = to do (gray), last = done (green),
 * anything between = in progress (blue). Without columns it stays gray.
 */
export default function StatusLozenge({ status, columns, className = '' }: { status: string; columns?: string[]; className?: string }) {
    const i = columns?.indexOf(status) ?? -1;
    const kind = !columns || i <= 0 ? 'todo' : i === columns.length - 1 ? 'done' : 'progress';

    return (
        <span
            className={`inline-block max-w-full truncate rounded-[3px] px-1 text-[11px] font-bold uppercase leading-4 ${styles[kind]} ${className}`}
        >
            {status}
        </span>
    );
}
