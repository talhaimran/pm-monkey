import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

// Jira-style colour tokens: [light, dark]. Each becomes a CSS variable (switched by `.dark`) and a Tailwind colour.
const tokens = {
    ink: ['#172B4D', '#B6C2CF'], // body text
    'ink-subtle': ['#44546F', '#9FADBC'],
    surface: ['#FFFFFF', '#1D2125'], // page
    sunken: ['#F7F8F9', '#161A1D'], // board columns, panels
    raised: ['#FFFFFF', '#22272B'], // cards, menus, dialogs
    line: ['#DCDFE4', '#38414A'], // borders
    'input-line': ['#8590A2', '#738496'], // form field borders
    brand: ['#0C66E4', '#579DFF'],
    'brand-hover': ['#0055CC', '#85B8FF'],
    'on-brand': ['#FFFFFF', '#1D2125'], // text on brand/danger buttons
    selected: ['#E9F2FF', '#1C2B41'],
    neutral: ['#F1F2F4', '#2C333A'], // default buttons, chips
    'neutral-hover': ['#DCDFE4', '#38414A'],
    danger: ['#C9372C', '#F87168'],
    success: ['#22A06B', '#4BCE97'],
    'loz-todo': ['#DCDFE4', '#454F59'],
    'loz-todo-ink': ['#44546F', '#B6C2CF'],
    'loz-progress': ['#E9F2FF', '#1C2B41'],
    'loz-progress-ink': ['#0055CC', '#85B8FF'],
    'loz-done': ['#DCFFF1', '#164B35'],
    'loz-done-ink': ['#216E4E', '#7EE2B8'],
};
const vars = (i) => Object.fromEntries(Object.entries(tokens).map(([name, v]) => [`--c-${name}`, v[i]]));

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
            fontFamily: {
                sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Oxygen', 'Ubuntu', '"Helvetica Neue"', 'sans-serif'],
            },
            boxShadow: { card: '0 1px 1px rgb(9 30 66 / 25%), 0 0 1px rgb(9 30 66 / 31%)' },
            colors: Object.fromEntries(Object.keys(tokens).map((name) => [name, `var(--c-${name})`])),
        },
    },

    plugins: [
        forms,
        plugin(({ addBase }) => addBase({ ':root': vars(0), '.dark': { ...vars(1), colorScheme: 'dark' } })),
    ],
};
