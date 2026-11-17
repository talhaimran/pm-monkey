import assert from 'node:assert/strict';
import { test } from 'node:test';
import { slugify } from './slug.ts';

test('slugify suggests a lowercase, hyphenated address', () => {
    assert.equal(slugify('Acme Inc.'), 'acme-inc');
    assert.equal(slugify('  Café  Crème '), 'cafe-creme');
    assert.equal(slugify('x'.repeat(39) + ' y'), 'x'.repeat(39));
    assert.equal(slugify('!!!'), '');
});
