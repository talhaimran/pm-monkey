import assert from 'node:assert/strict';
import test from 'node:test';
import ds from '../../docs/design-system/tokens.json' with { type: 'json' };

type Theme = 'light' | 'dark';
const value = (name: string, theme: Theme) => (ds.color.tokens.find((t) => t.name === name)!.value as Record<Theme, string>)[theme];

/** #rrggbb or #rrggbbaa (composited over `under`) to [r, g, b]. */
function rgb(hex: string, under: number[] = [0, 0, 0]): number[] {
    const n = [1, 3, 5, 7].map((i) => parseInt(hex.slice(i, i + 2), 16));
    const a = Number.isNaN(n[3]) ? 1 : n[3] / 255;
    return [0, 1, 2].map((i) => n[i] * a + under[i] * (1 - a));
}
const mix = (a: number[], b: number[], share: number) => a.map((v, i) => v * share + b[i] * (1 - share));
function luminance(c: number[]): number {
    const [r, g, b] = c.map((v) => (v / 255 <= 0.03928 ? v / 255 / 12.92 : ((v / 255 + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
function contrast(a: number[], b: number[]): number {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}

for (const theme of ['light', 'dark'] as const) {
    const on = (fg: string, bg: string) => contrast(rgb(value(fg, theme), rgb(value(bg, theme))), rgb(value(bg, theme)));

    test(`${theme}: reading text passes 4.5:1 on every surface`, () => {
        for (const fg of ['text', 'muted']) for (const bg of ['card', 'card-2', 'bg']) assert.ok(on(fg, bg) >= 4.5, `${fg} on ${bg}: ${on(fg, bg).toFixed(2)}`);
        assert.ok(on('on-primary', 'primary') >= 4.5);
        assert.ok(on('on-ink', 'ink') >= 4.5);
        assert.ok(on('side-dim', 'side') >= 4.5, `side-dim on side: ${on('side-dim', 'side').toFixed(2)}`);
        assert.ok(on('on-side-pill', 'side-pill') >= 4.5);
    });

    test(`${theme}: pill text passes 4.5:1 on its own tint`, () => {
        const card = rgb(value('card', theme));
        for (const hue of ['amber', 'cobalt', 'sky', 'coral', 'teal', 'slate', 'sand', 'good', 'warn', 'bad', 'info']) {
            const tint = mix(rgb(value(`c-${hue}`, theme)), card, theme === 'light' ? 0.14 : 0.18);
            const ratio = contrast(rgb(value(`t-${hue}`, theme)), tint);
            assert.ok(ratio >= 4.5, `t-${hue}: ${ratio.toFixed(2)}`);
        }
    });

    test(`${theme}: avatar initials pass 4.5:1`, () => {
        for (const hue of ['amber', 'cobalt', 'coral', 'sand', 'sky', 'slate', 'teal']) {
            assert.ok(contrast(rgb(value(`av-${hue}-t`, theme)), rgb(value(`av-${hue}`, theme))) >= 4.5, hue);
        }
    });
}
