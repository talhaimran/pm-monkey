# Dashboard widget

One block on a project or workspace dashboard: sprint health, issues by status, workload by person, recent activity, a saved filter's results.

**Anatomy:** `.widget` with a head (title, one-line subtitle naming the scope: "Sprint 14 · Web", and a menu `.ibtn` for Edit, Duplicate, Remove), then one body: a KPI `.tile` row, `.bars` (labelled bars in the project's unit), a `.wlist` of issues, or a chart. Charts follow the dataviz rules in `README.md` and use the category and type colours, never new ones.

**Sizes:** 1, 2, or 3 columns wide on a 3-column grid; one column under 760px. Heights fit their content; lists cap at 8 rows with "View all".

**Behaviour:** numbers are live and update in place without animation beyond a 200ms fade. Clicking a bar or a number opens the matching filtered list.

**Empty:** "No issues match this widget's filter." with "Edit filter".

**A11y:** each bar row reads as text ("In Progress, 12 issues"); charts have a table alternative.
