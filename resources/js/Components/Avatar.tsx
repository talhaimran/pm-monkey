import { Sparkles } from 'lucide-react';

// One of seven design system pairs, stable per email. Written out in full so Tailwind sees every class.
const pairs = [
    'bg-av-amber text-av-amber-t',
    'bg-av-cobalt text-av-cobalt-t',
    'bg-av-coral text-av-coral-t',
    'bg-av-sand text-av-sand-t',
    'bg-av-sky text-av-sky-t',
    'bg-av-slate text-av-slate-t',
    'bg-av-teal text-av-teal-t',
];
const sizes = { xs: 'h-6 w-6 text-2xs', sm: 'h-8 w-8 text-xs', md: 'h-10 w-10 text-md', lg: 'h-[52px] w-[52px] text-lg' };

/** Initials in a tinted disc; the colour is stable per email. `agent` is the design system's av--agent: an ink rounded square with a spark, never an assignee. */
export default function Avatar({ name, email = '', size = 'xs', agent = false, className = '' }: { name: string; email?: string; size?: keyof typeof sizes; agent?: boolean; className?: string }) {
    if (agent) {
        return (
            <span
                title={name}
                aria-hidden="true"
                className={`inline-flex shrink-0 select-none items-center justify-center rounded-[30%] bg-ink text-on-ink ${sizes[size]} ${className}`}
            >
                <Sparkles className="h-[55%] w-[55%]" />
            </span>
        );
    }

    const sum = [...email.toLowerCase()].reduce((n, ch) => n + ch.charCodeAt(0), 0);
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <span
            title={name}
            aria-label={name}
            role="img"
            className={`inline-flex shrink-0 select-none items-center justify-center rounded-pill ${pairs[sum % pairs.length]} ${sizes[size]} ${className}`}
        >
            {initials || '?'}
        </span>
    );
}
