# Issue page

The full page for one issue at `/{workspace}/issues/{KEY}`. The peek drawer (`?issue={KEY}`) shows the same content in less room; "Open full page" goes here.

**Layout:** `.ipage`, two columns: main (minmax 0, 1fr) and a 320px side. Under 760px the side moves above the description.

**Main:** `.ipage__crumbs` (project › parent epic › key), the editable title (`.ipage__title`), then sections: Description (rich text), Sub-tasks (compact issue cards), Linked issues, Attachments, and Activity (`.act`: comments and changes in one list, newest last, with a comment box at the bottom). Agent entries use the agent avatar and are labelled "(AI)".

**Side:** the status pill menu button and the primary action, then the AI box (approvals waiting, recent agent runs, Ask agent; never an automatic summary), then `.flist` with the fields: Assignee, Reporter, Priority, Estimate, Sprint, Labels, Due, then "Show 6 more fields".

**Header actions:** Copy link, Watch, the overflow menu (Move, Clone, Delete). Delete is soft, with a toast and Undo.

**Live updates:** another person's changes update fields in place with a short highlight; the field being edited is never replaced (a conflict dialog appears on save instead).

**A11y:** one `h1` (the title); each section is headed. The status control is a Headless UI `Menu`.
