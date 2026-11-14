import { LucideIcon } from 'lucide-react';
import SecondaryButton from './SecondaryButton';

/**
 * Design system empty state: what goes here and how to add it, with at most one button.
 * `compact` is the small dashed target used inside board columns and sprint lists.
 */
export default function EmptyState({
    icon: Icon,
    title,
    hint,
    action,
    compact = false,
}: {
    icon?: LucideIcon;
    title: string;
    hint?: string;
    action?: { label: string; onClick: () => void; shortcut?: string };
    compact?: boolean;
}) {
    if (compact) {
        return (
            <p className="rounded-item border border-dashed border-line-2 px-3 py-4 text-center text-sm text-muted">
                {title}
                {hint && <span className="block">{hint}</span>}
            </p>
        );
    }

    return (
        <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-line-2 px-6 py-10 text-center">
            {Icon && (
                <span className="mb-1 flex h-10 w-10 items-center justify-center rounded-tile bg-card-2 text-muted">
                    <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
            )}
            <p className="text-lg text-text">{title}</p>
            {hint && <p className="text-muted">{hint}</p>}
            {action && (
                <SecondaryButton onClick={action.onClick} className="mt-2">
                    {action.label}
                    {action.shortcut && <kbd className="rounded-xs bg-card-2 px-1.5 text-2xs text-muted">{action.shortcut}</kbd>}
                </SecondaryButton>
            )}
        </div>
    );
}
