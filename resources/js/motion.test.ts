import assert from 'node:assert/strict';
import test from 'node:test';
import { moments, stagger, transition } from './motion.ts';

test('reduced motion makes every duration 0', () => {
    for (const moment of moments) assert.equal(transition(moment, true).duration, 0, moment);
    assert.equal(stagger(true), 0);
});

test('timings come from the design system tokens', () => {
    assert.deepEqual(transition('drawerIn', false), { duration: 0.55, ease: [0.165, 0.84, 0.44, 1] });
    assert.deepEqual(transition('dialogIn', false), { duration: 0.45, ease: [0.34, 1.56, 0.64, 1] });
    assert.deepEqual(transition('dialogOut', false), { duration: 0.18, ease: [0.55, 0.085, 0.68, 0.53] });
    assert.equal(transition('lift', false).duration, 0.25);
    assert.equal(stagger(false), 0.04);
});
