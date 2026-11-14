import { ChevronDown, ChevronsDown, ChevronsUp, ChevronUp, Equal, LucideIcon } from 'lucide-react';

// Distinct shapes per priority, so colour is never the only signal.
const shapes: Record<string, { color: string; Icon: LucideIcon }> = {
    highest: { color: 'text-t-bad', Icon: ChevronsUp },
    high: { color: 'text-t-coral', Icon: ChevronUp },
    medium: { color: 'text-t-amber', Icon: Equal },
    low: { color: 'text-t-sky', Icon: ChevronDown },
    lowest: { color: 'text-t-slate', Icon: ChevronsDown },
};

export default function PriorityIcon({ priority, className = '' }: { priority: string; className?: string }) {
    const { color, Icon } = shapes[priority] ?? shapes.medium;

    return (
        <span role="img" aria-label={`Priority: ${priority}`} title={`Priority: ${priority}`} className={`inline-flex shrink-0 ${color} ${className}`}>
            <Icon className="h-4 w-4" aria-hidden="true" />
        </span>
    );
}
