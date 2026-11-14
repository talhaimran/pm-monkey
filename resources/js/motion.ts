// Every animation timing in the app. Durations and easings come from the design system tokens
// (docs/design-system/tokens.json, catalogued in motion.md). When the OS asks for reduced motion,
// every duration is 0 and content still appears; app.css holds the same switch for plain CSS transitions.
import ds from '../../docs/design-system/tokens.json' with { type: 'json' };

type Bezier = [number, number, number, number];

const seconds = (name: string) => parseInt(ds.duration.tokens.find((t) => t.name === name)!.value) / 1000;
const bezier = (name: string) => ds.easing.tokens.find((t) => t.name === name)!.value.match(/-?[\d.]+/g)!.map(Number) as Bezier;

// motion.md catalog. Values with no dur-* token (240, 300, 320, 480ms) live only here.
const catalog = {
    drawerIn: [seconds('dur-enter'), bezier('ease-out-strong')],
    drawerOut: [0.24, bezier('ease-in')],
    dialogIn: [seconds('dur-base'), bezier('ease-overshoot')],
    dialogOut: [seconds('dur-exit'), bezier('ease-in')],
    toast: [0.3, bezier('ease-out')],
    lift: [seconds('dur-short'), bezier('ease-overshoot')],
    drop: [0.32, bezier('ease-out')],
    theme: [0.48, bezier('ease-standard')],
} satisfies Record<string, [number, Bezier]>;

export type Moment = keyof typeof catalog;
export const moments = Object.keys(catalog) as Moment[];

/** The one reduced-motion switch. */
export function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** A Motion `transition` for a catalogued moment. */
export function transition(moment: Moment, reduced = prefersReducedMotion()): { duration: number; ease: Bezier } {
    const [duration, ease] = catalog[moment];
    return { duration: reduced ? 0 : duration, ease };
}

/** Delay between list items, in seconds. */
export function stagger(reduced = prefersReducedMotion()): number {
    return reduced ? 0 : seconds('stagger');
}
