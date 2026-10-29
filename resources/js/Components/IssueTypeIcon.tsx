// Jira-style 16px issue type badges: coloured square with a white glyph.
const icons: Record<string, { bg: string; label: string; glyph: React.ReactNode }> = {
    story: { bg: '#63BA3C', label: 'Story', glyph: <path d="M5 4h6v8l-3-2-3 2z" fill="#fff" /> },
    bug: { bg: '#E5493A', label: 'Bug', glyph: <circle cx="8" cy="8" r="3" fill="#fff" /> },
    task: { bg: '#4BADE8', label: 'Task', glyph: <path d="m5 8.2 2 2 4-4.4" fill="none" stroke="#fff" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" /> },
    epic: { bg: '#904EE2', label: 'Epic', glyph: <path d="M9 3 5 9h3l-1 4 4-6H8z" fill="#fff" /> },
    subtask: {
        bg: '#79B8F0',
        label: 'Sub-task',
        glyph: <path d="M5 5h3v3H5zM8 8h3v3H8z" fill="none" stroke="#fff" strokeWidth="1.4" />,
    },
};

/** `subtask` wins over `type`, matching Jira where a sub-task has its own icon. */
export default function IssueTypeIcon({ type, subtask = false, className = '' }: { type: string; subtask?: boolean; className?: string }) {
    const icon = icons[subtask ? 'subtask' : type] ?? icons.task;

    return (
        <svg viewBox="0 0 16 16" className={`h-4 w-4 shrink-0 ${className}`} role="img" aria-label={icon.label}>
            <title>{icon.label}</title>
            <rect width="16" height="16" rx="3" fill={icon.bg} />
            {icon.glyph}
        </svg>
    );
}
