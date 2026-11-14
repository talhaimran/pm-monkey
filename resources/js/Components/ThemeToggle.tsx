import { prefersReducedMotion, transition } from '@/motion';
import { Monitor, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { flushSync } from 'react-dom';

type Theme = 'light' | 'dark' | 'system';
const next: Record<Theme, Theme> = { light: 'dark', dark: 'system', system: 'light' };
const icons = { light: Sun, dark: Moon, system: Monitor };

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

export default function ThemeToggle({ className = '' }: { className?: string }) {
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

    // The new theme cross-fades in through the View Transitions API (timed in app.css from --theme-duration).
    // shortcut: a plain cross-fade, not the design system's circle reveal from the toggle.
    const toggle = () => {
        const change = () => setTheme(next[theme]);
        const doc = document as Document & { startViewTransition?: (update: () => void) => unknown };
        if (!doc.startViewTransition || prefersReducedMotion()) return change();
        document.documentElement.style.setProperty('--theme-duration', `${transition('theme').duration}s`);
        doc.startViewTransition(() => flushSync(change));
    };

    const Icon = icons[theme];

    return (
        <button type="button" onClick={toggle} className={className} aria-label={`Theme: ${theme}. Switch to ${next[theme]}`}>
            <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
            <span className="truncate">Theme: {theme}</span>
        </button>
    );
}
