import { Bookmark, Bug, CornerDownRight, LucideIcon, SquareCheck, Zap } from 'lucide-react';

// Design system type chips: a Lucide icon on the type's tint. The label makes sure colour is never the only signal.
const icons: Record<string, { tint: string; label: string; Icon: LucideIcon }> = {
    task: { tint: 'tint-slate', label: 'Task', Icon: SquareCheck },
    story: { tint: 'tint-cobalt', label: 'Story', Icon: Bookmark },
    bug: { tint: 'tint-coral', label: 'Bug', Icon: Bug },
    epic: { tint: 'tint-amber', label: 'Epic', Icon: Zap },
    subtask: { tint: 'tint-sky', label: 'Sub-task', Icon: CornerDownRight },
};

/** `subtask` wins over `type`: a sub-task has its own icon. */
export default function IssueTypeIcon({ type, subtask = false, className = '' }: { type: string; subtask?: boolean; className?: string }) {
    const { tint, label, Icon } = icons[subtask ? 'subtask' : type] ?? icons.task;

    return (
        <span role="img" aria-label={label} title={label} className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-xs ${tint} ${className}`}>
            <Icon className="h-3.5 w-3.5" aria-hidden="true" />
        </span>
    );
}
