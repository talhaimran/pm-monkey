import { useEffect } from 'react';

export const CREATE_ISSUE_EVENT = 'pm:create-issue';

/** Asks the current page to open its create-issue dialog ("c" or "+ Create"). False when no page handled it. */
export const requestCreate = () => !window.dispatchEvent(new Event(CREATE_ISSUE_EVENT, { cancelable: true }));

/** Runs `open` when the layout's "c" shortcut or "+ Create" button fires on this page. */
export function useCreateShortcut(open: () => void, enabled = true) {
    useEffect(() => {
        if (!enabled) return;
        const handle = (e: Event) => {
            e.preventDefault();
            open();
        };
        window.addEventListener(CREATE_ISSUE_EVENT, handle);
        return () => window.removeEventListener(CREATE_ISSUE_EVENT, handle);
    }, [open, enabled]);
}
