import { useEffect } from 'react';

export const CREATE_ISSUE_EVENT = 'pm:create-issue';

/** Runs `open` when the layout's "c" shortcut fires on this page. */
export function useCreateShortcut(open: () => void, enabled = true) {
    useEffect(() => {
        if (!enabled) return;
        window.addEventListener(CREATE_ISSUE_EVENT, open);
        return () => window.removeEventListener(CREATE_ISSUE_EVENT, open);
    }, [open, enabled]);
}
