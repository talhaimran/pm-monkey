import forms from '@tailwindcss/forms';
import { readFileSync } from 'node:fs';
import plugin from 'tailwindcss/plugin';

// The design system is the single source of tokens (docs/design-system/tokens.json).
// Colours and shadows become CSS variables (light on :root, dark on .dark) and Tailwind names.
const ds = JSON.parse(readFileSync(new URL('./docs/design-system/tokens.json', import.meta.url), 'utf8'));
const px = (group, strip) => Object.fromEntries(ds[group].tokens.map((t) => [t.name.replace(strip, ''), t.value]));

const themed = [...ds.color.tokens, ...ds.shadow.tokens];
const vars = (theme) => Object.fromEntries(themed.map((t) => [`--${t.name}`, t.value[theme] ?? t.value.light]));

const fontSize = Object.fromEntries(
    ds.type.groups
        .flatMap((g) => g.styles)
        .map((s) => [
            s.name.replace(/^text-/, ''),
            [s.fontSize, { lineHeight: s.lineHeight, fontWeight: String(s.fontWeight), letterSpacing: s.letterSpacing ?? '0' }],
        ]),
);

// Pills, chips and type icons: the strong colour tinted into card (14% light, 18% dark) with the matching text token.
const hues = ['amber', 'cobalt', 'sky', 'coral', 'teal', 'slate', 'sand', 'good', 'warn', 'bad', 'info'];
const tints = Object.fromEntries(
    hues.map((h) => [`.tint-${h}`, { backgroundColor: `color-mix(in srgb, var(--c-${h}) var(--tint), var(--card))`, color: `var(--t-${h})` }]),
);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            // Spec 5.1: under 760px the sidebar is a sheet and drawers and dialogs rise from the bottom.
            screens: { md: '760px' },
            fontFamily: { sans: ds.type.families.sans.split(',').map((f) => f.trim()) },
            fontSize,
            colors: Object.fromEntries(ds.color.tokens.map((t) => [t.name, `var(--${t.name})`])),
            boxShadow: Object.fromEntries(ds.shadow.tokens.map((t) => [t.name.replace(/^shadow-?/, '') || 'DEFAULT', `var(--${t.name})`])),
            borderRadius: px('radius', 'radius-'),
            // Fixed component sizes: h-control-h, h-input-h, w-side-w, w-drawer-w, min-w-column-min-w…
            spacing: px('size', /^$/),
            transitionDuration: px('duration', 'dur-'),
            transitionTimingFunction: px('easing', 'ease-'),
        },
    },

    plugins: [
        forms,
        plugin(({ addBase, addUtilities }) => {
            addBase({ ':root': { ...vars('light'), '--tint': '14%' }, '.dark': { ...vars('dark'), '--tint': '18%', colorScheme: 'dark' } });
            addUtilities(tints);
            // Scrim behind dialogs, drawers and sheets (token colours are CSS variables, so /40 opacity classes don't exist).
            addUtilities({ '.scrim': { backgroundColor: 'color-mix(in srgb, #000 45%, transparent)' } });
        }),
    ],
};
