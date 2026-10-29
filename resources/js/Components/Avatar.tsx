const colours = ['#0C66E4', '#1F845A', '#C9372C', '#6E5DC6', '#A54800', '#206A83', '#943D73', '#5B7F24'];

/** Round initials avatar; the colour is stable per email. */
export default function Avatar({ name, email, size = 'sm', className = '' }: { name: string; email: string; size?: 'sm' | 'md'; className?: string }) {
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
            style={{ backgroundColor: colours[sum % colours.length] }}
            className={`inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold text-white ${
                size === 'md' ? 'h-8 w-8 text-xs' : 'h-6 w-6 text-[10px]'
            } ${className}`}
        >
            {initials || '?'}
        </span>
    );
}
