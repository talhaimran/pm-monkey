import { useEffect, useState } from 'react';

type Theme = 'light' | 'dark' | 'system';
const next: Record<Theme, Theme> = { light: 'dark', dark: 'system', system: 'light' };
const icons: Record<Theme, string> = { light: '☀', dark: '☾', system: '◐' };

function read(): Theme {
    try {
        const saved = localStorage.getItem('theme');
        return saved === 'light' || saved === 'dark' ? saved : 'system';
    } catch {
        return 'system';
    }
}

/** Mirrors the inline script in app.blade.php, which applies the theme before first paint. */
function apply(theme: Theme) {
    const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

export default function ThemeToggle() {
    const [theme, setTheme] = useState<Theme>(read);

    useEffect(() => {
        apply(theme);
        try {
            if (theme === 'system') localStorage.removeItem('theme');
            else localStorage.setItem('theme', theme);
        } catch {
            // Storage blocked (private mode): the choice lasts for this page only.
        }
        if (theme !== 'system') return;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const follow = () => apply('system');
        media.addEventListener('change', follow);
        return () => media.removeEventListener('change', follow);
    }, [theme]);

    return (
        <button
            type="button"
            onClick={() => setTheme(next[theme])}
            className="flex h-8 w-8 items-center justify-center rounded-full text-base text-ink-subtle hover:bg-neutral hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            title={`Theme: ${theme} (click for ${next[theme]})`}
            aria-label={`Theme: ${theme}. Switch to ${next[theme]}`}
        >
            <span aria-hidden="true">{icons[theme]}</span>
        </button>
    );
}
