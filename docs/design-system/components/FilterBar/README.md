# Filter bar

The row of filters above a board, list, or search: each active filter is a chip with a field, a value, and a remove button.

**Anatomy:** `.fbar` with a search field, then one `.fchip` per filter: `.fchip__key` ("Assignee"), `.fchip__val` (a menu button: "Talha, Sam"), `.fchip__x`. After the chips: "Add filter" (small ghost), and on the right "Save view" and "Clear". Quick toggles ("Only my issues", "Recently updated") use the toggle `.chip`.

**Behaviour:** filters are in the URL, so a filtered view can be shared or bookmarked. The value menu is a Headless UI `Listbox` (multi-select) or a date picker. Changing a filter updates the results in place; focus stays in the menu.

**Values:** several values in one chip are OR; separate chips are AND. Over two values the chip shows "3 assignees".

**A11y:** each chip's remove button is labelled ("Remove Assignee filter"). The results count is announced politely after a change.
