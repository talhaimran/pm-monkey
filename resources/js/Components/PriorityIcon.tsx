const shapes: Record<string, { color: string; paths: string[] }> = {
    highest: { color: '#E5493A', paths: ['M3 8l5-4 5 4', 'M3 12l5-4 5 4'] },
    high: { color: '#E5493A', paths: ['M3 10l5-4 5 4'] },
    medium: { color: '#E56910', paths: ['M3 6h10', 'M3 10h10'] },
    low: { color: '#2684FF', paths: ['M3 6l5 4 5-4'] },
    lowest: { color: '#2684FF', paths: ['M3 4l5 4 5-4', 'M3 8l5 4 5-4'] },
};

/** Jira-style priority glyph: red chevrons up, orange equals, blue chevrons down. */
export default function PriorityIcon({ priority, className = '' }: { priority: string; className?: string }) {
    const shape = shapes[priority] ?? shapes.medium;

    return (
        <svg viewBox="0 0 16 16" className={`h-4 w-4 shrink-0 ${className}`} role="img" aria-label={`Priority: ${priority}`}>
            <title>{`Priority: ${priority}`}</title>
            {shape.paths.map((d) => (
                <path key={d} d={d} fill="none" stroke={shape.color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            ))}
        </svg>
    );
}
