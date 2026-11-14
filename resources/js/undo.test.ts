import assert from 'node:assert/strict';
import test from 'node:test';
import { isTyping, lifetime, push, undoTarget, type Toast } from './undo.ts';

const undo = { message: 'WEB-1 moved to Done', method: 'post' as const, url: '/issues/WEB-1/move', data: { undo_of: 7 } };

test('at most three toasts are stacked, newest last', () => {
    let toasts: Toast[] = [];
    for (const message of ['a', 'b', 'c', 'd']) toasts = push(toasts, { message, tone: 'plain' });

    assert.deepEqual(toasts.map((t) => t.message), ['b', 'c', 'd']);
    assert.equal(new Set(toasts.map((t) => t.id)).size, 3);
});

test('a toast stays 6s with Undo and 4.2s without', () => {
    const [withUndo, without] = push(push([], { message: 'x', tone: 'plain', undo }), { message: 'y', tone: 'bad' });

    assert.equal(lifetime(withUndo), 6000);
    assert.equal(lifetime(without), 4200);
});

test('the keyboard shortcut undoes the newest undoable toast, once', () => {
    let toasts = push([], { message: 'first', tone: 'plain', undo });
    toasts = push(toasts, { message: 'second', tone: 'plain', undo: { ...undo, url: '/issues/WEB-2/move' } });
    toasts = push(toasts, { message: 'failed', tone: 'bad' });

    const target = undoTarget(toasts)!;
    assert.equal(target.message, 'second');
    assert.equal(undoTarget(toasts.filter((t) => t.id !== target.id))!.message, 'first');
    assert.equal(undoTarget([]), undefined);
});

test('typing in a field keeps the native text undo', () => {
    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) assert.equal(isTyping({ tagName }), true);
    assert.equal(isTyping({ tagName: 'DIV', isContentEditable: true }), true);
    assert.equal(isTyping({ tagName: 'BUTTON' }), false);
    assert.equal(isTyping(null), false);
});
