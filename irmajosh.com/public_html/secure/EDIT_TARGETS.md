Edit targets for the secure dashboard

Instructions
- Edit this file locally and paste the updated file content back in chat when ready.
- I will use the edited sections to inject exact changes into the codebase.

1) DOM containers and IDs (these are created in `ui.js`):
- #tabs-root (root wrapper for tabs + content)
- #pane-jobs (main jobs pane)
- #left-col (left column, contains #forms-top -> #forms-root)
- #forms-top (container where forms are appended)
- #forms-root (wrapper for the two forms)
- #irma-form, #josh-form (forms)
- #irma-title, #irma-notes, #josh-title, #josh-notes (form inputs)
- #center-col (center column: #available-jobs, #accepted-jobs)
- #available-jobs
- #accepted-jobs
- #right-col (right column header + #pending-requests)
- #pending-requests
- #calendar-wrap, #calendar

2) CSS selectors we changed (edit these values to change spacing, widths, colors):
- Desktop grid:
  - #pane-jobs { grid-template-columns: 360px 1fr 360px }
  - @media(min-width:1000px) overrides near the end
- Left column & form card:
  - #left-col, #forms-root, #forms-root .card
- Center column & accepted jobs:
  - #center-col, #accepted-jobs, #accepted-jobs .card
- Right column & pending requests:
  - #right-col, #pending-requests, #pending-requests .card
- Buttons:
  - .btn-primary, .btn-schedule

3) JS hooks and functions to edit:
- initApp() in `public_html/secure/js/ui.js` - builds the skeleton; edit its inner HTML if you want to change the order or markup of elements.
- renderList(root, items, type) - controls how job/request cards are built; edit to change inner structure (title/notes/button order, classes assigned).
- refreshLists() - updates tab badges and calls renderList; it expects API responses with .items arrays.
- openScheduleModal(sourceId, sourceType, title, date, activeTab) - from `calendar.js`, opens modal and expects worker availability logic.

4) Example CSS edits you can paste back here (replace values as needed):

/* Make left column wider */
#pane-jobs { grid-template-columns: 420px 1fr 360px }

/* Make Post a Request fill vertical space */
#left-col { min-height: 520px }
#forms-root .card { display:flex; flex-direction:column; justify-content:space-between }

/* Center accepted cards */
#accepted-jobs { display:flex; flex-direction:column; align-items:center }
#accepted-jobs .card { max-width:380px }

/* Pending requests: centered text */
#pending-requests .card { text-align:center }
#pending-requests .card .notes { color:#e6eef8 }

5) If you want me to inject JS changes, place them in a fenced block below with the filename to edit, like:

---
FILE: public_html/secure/js/ui.js
```js
// paste replacement function or snippet here
```
---

When you paste back your edits, I'll apply them exactly to the codebase and run quick sanity checks. If you'd rather I produce a single patch with suggested edits, tell me and I'll create it directly.
