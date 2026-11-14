import { router } from '@inertiajs/react';
import { MouseEvent } from 'react';

// One URL scheme for issues (spec 5.1): the full page is /issues/{KEY}; the peek drawer is the current page plus ?issue={KEY}.
// Shared links always use the full page URL.

/** The canonical URL of an issue's full page. */
export const issueUrl = (key: string) => route('issues.show', key);

/** The current page with the peek drawer open on `key`, or closed for null. */
export function peekUrl(key: string | null): string {
    const url = new URL(window.location.href);
    if (key) url.searchParams.set('issue', key);
    else url.searchParams.delete('issue');
    return url.pathname + url.search;
}

/** Open (or with null, close) the peek drawer without losing the page's place. Back and reload work, since it is a URL. */
export function peek(key: string | null) {
    router.get(peekUrl(key), {}, { preserveState: true, preserveScroll: true, only: ['peek', 'flash', 'errors'] });
}

/** onClick for a link to an issue: a plain click peeks; Cmd/Ctrl/Shift-click and middle click keep the browser's behaviour. */
export const onPeekClick = (key: string) => (e: MouseEvent) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    peek(key);
};
