# Frontend Audit Report

**Project:** Laravel + Inertia.js + Vue 3 + Vuetify 3 — Project Management App  
**Date:** 2025  
**Scope:** All files under `resources/js/`, `resources/css/`, and frontend config files

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 7     |
| Medium   | 22    |
| Low      | 14    |

---

## CRITICAL Issues

### 1. `isAdmin` referenced but never defined — Settings page broken for non-admin check
- **File:** `resources/js/Pages/Workspaces/Settings.vue`, line 191
- **Issue:** The template uses `v-if="isAdmin"` to conditionally show the "Add Member" button, but `isAdmin` is never declared as a `ref`, `computed`, or `prop` in `<script setup>`. This causes a Vue runtime warning and the button never renders.
- **Fix:** Add a computed property: `const isAdmin = computed(() => props.workspace?.pivot?.role === 'admin' || ...);` that derives from the current user's role in the workspace.

### 2. `handleTaskComplete` is a no-op on Dashboard and MyTasks — broken user feedback
- **File:** `resources/js/Pages/Dashboard.vue`, line 112–114
- **File:** `resources/js/Pages/Tasks/MyTasks.vue`, line 133–135
- **Issue:** Both pages bind `@complete="handleTaskComplete"` on `TaskCard`, but the handler is an empty function with only a comment. Users click the complete action and nothing happens — no visual change, no error message, no toast. This is confusing UX.
- **Fix:** Either remove the completion affordance from TaskCard when displaying tasks (not subtasks), or display a tooltip/snackbar explaining that only subtasks can be completed.

### 3. Filters declared but never applied to board/list views
- **File:** `resources/js/Pages/Lists/Show.vue`, lines 80–83
- **Issue:** `filterStatus`, `filterPriority`, `filterAssignee`, and `searchQuery` refs are declared, and filter UI controls are rendered, but the filtered data is never computed or passed to `StatusColumn`. The board always shows all tasks regardless of filter selections.
- **Fix:** Create a `filteredTasksByStatus` computed property that applies filters and pass it to the template instead of `localTasksByStatus`.

### 4. Full Vuetify component/directive import — massive bundle size
- **File:** `resources/js/plugins/vuetify.js`, lines 9–10
- **Issue:** `import * as components from 'vuetify/components'` and `import * as directives from 'vuetify/directives'` imports the entire Vuetify library (~700KB+ gzipped). This severely impacts initial load time and defeats tree-shaking.
- **Fix:** Switch to `vuetify-loader` / `vite-plugin-vuetify` for automatic tree-shaking, or manually import only the components used.

### 5. TimeTracking `saveEntry` has no create path — "Add Manual Entry" button is broken
- **File:** `resources/js/Pages/TimeTracking/Index.vue`, lines 125–140
- **Issue:** The `saveEntry()` function only handles the `editingEntry.value` (edit) path. When a user clicks "Add Manual Entry" (which sets `editingEntry.value = null`), the function does nothing because there is no `else` branch to POST a new entry.
- **Fix:** Add an `else` branch that `router.post(...)` to a create endpoint with the form data.

### 6. Calendar TaskDetailPanel receives unreliable nested props
- **File:** `resources/js/Pages/Calendar/Index.vue`, lines 304–316
- **Issue:** The `space` prop is passed as `selectedTask?.task?.taskList?.space` and `list` as `selectedTask?.task?.taskList`, relying on deeply nested relationships that may not be eager-loaded from the backend. If these are `undefined`, the TaskDetailPanel will fail silently on any action that needs workspace/space/list context (status changes, priority changes, etc.).
- **Fix:** Either ensure the backend eager-loads these relationships for calendar subtasks, or pass the workspace-level data directly.

### 7. Delete list has no confirmation dialog
- **File:** `resources/js/Pages/Lists/Show.vue`, `confirmDeleteList()` function (~line 317)
- **Issue:** `confirmDeleteList()` immediately calls `router.delete()` without any guard. While `showDeleteList` ref exists and gates the dialog button, the function itself performs no validation that the dialog was actually confirmed. A programmatic call would bypass the UI guard. More critically—deleting a list is an irreversible destructive action that removes all tasks within it, yet relies only on a simple dialog open/close (no typed confirmation like workspace delete).
- **Fix:** Add typed confirmation (like the workspace delete pattern) to verify intent before destroying a list and all its contents.

---

## MEDIUM Issues

### 8. `window.showSnackbar` global function anti-pattern
- **Files:** 50+ usages across `TaskDetailPanel.vue`, `Settings.vue`, `Dashboard.vue`, `Lists/Show.vue`, `Workspaces/Show.vue`, `MainLayout.vue`, etc.
- **Issue:** Every component calls `window.showSnackbar(...)` for toast notifications. This couples all components to a global side-effect, is not testable, breaks SSR compatibility, and fails silently until `MainLayout` mounts and assigns it.
- **Severity:** Medium
- **Fix:** Use Vue's `provide`/`inject` pattern or a Pinia store for notifications.

### 9. `window.openCreateSpaceDialog` — same global anti-pattern
- **File:** `resources/js/Layouts/MainLayout.vue`, line 164; consumed in `Dashboard.vue`, line 170
- **Issue:** Same as #8 but for opening the create space dialog.
- **Severity:** Medium

### 10. Font family triple-conflict
- **File:** `tailwind.config.js`, line 19 — sets `Figtree`
- **File:** `resources/css/app.css`, lines 10–12 — sets `Source Sans 3`
- **File:** `resources/js/plugins/vuetify.js`, line 68 — sets `Inter, Source Sans 3`
- **Issue:** Three different font families are declared across three config systems. The CSS `html, body` rule wins for most elements, Vuetify components use their own font config, and Tailwind's `font-sans` utility uses Figtree. This leads to inconsistent typography.
- **Severity:** Medium
- **Fix:** Standardize on one font family across all three systems.

### 11. `AppLayout.vue` is dead code for most of the app
- **File:** `resources/js/Layouts/AppLayout.vue` (~250 lines)
- **Issue:** Only used by `Profile/Show.vue` and `API/Index.vue` (Jetstream defaults). All custom pages use `MainLayout.vue`. This creates a jarring UX inconsistency — the Profile and API page have completely different navigation, sidebar, and styling compared to the rest of the app.
- **Severity:** Medium
- **Fix:** Migrate Profile and API pages to use `MainLayout` or integrate their styling/navigation.

### 12. Native `confirm()` used instead of Vuetify dialogs — 10 occurrences
- **Files:**
  - `Workspaces/Settings.vue` lines 65, 108
  - `TaskDetailPanel.vue` lines 392, 1111, 1220
  - `TimeTracking/Index.vue` line 143
  - `StatusColumn.vue` line 150
  - `Tasks/Show.vue` lines 161, 343
  - `Sprints/Index.vue` line 139
- **Issue:** Browser `confirm()` dialogs are unstyled, block the main thread, look out-of-place in a polished dark-theme app, and cannot be customized or tested.
- **Severity:** Medium
- **Fix:** Replace with `v-dialog` confirmation components.

### 13. Create button in navbar does nothing
- **File:** `resources/js/Layouts/MainLayout.vue`, lines 224–228
- **Issue:** The "Create" button in the top nav bar (`<v-btn color="primary">`) has no `@click` handler. It renders but is completely non-functional.
- **Severity:** Medium
- **Fix:** Attach an action (e.g., open a quick-create menu for tasks/spaces/lists).

### 14. Notification bell is non-functional
- **File:** `resources/js/Layouts/MainLayout.vue`, lines 230–234
- **Issue:** The bell icon has a red dot badge suggesting notifications exist, but clicking it does nothing — no dropdown, no navigation, no handler. This gives false expectations.
- **Severity:** Medium
- **Fix:** Either implement notifications or remove the badge dot to avoid misleading users.

### 15. No accessibility (a11y) throughout the app
- **Files:** All Vue components
- **Issue:** No `aria-label`, `aria-describedby`, `role` attributes on custom interactive elements. Drag-and-drop (vuedraggable) has no keyboard alternative. Color-only indicators (priority flags, status colors) have no text alternatives. Focus management is absent in modal dialogs and slide-over panels.
- **Severity:** Medium
- **Fix:** Add ARIA attributes, keyboard navigation support, focus trapping in dialogs, and screen-reader text for color-coded indicators.

### 16. Login page missing Register and Forgot Password links
- **File:** `resources/js/Pages/Auth/Login.vue`
- **Issue:** The login form has no visible links to registration or password reset pages, even though `Register.vue` and related auth pages exist. Users who need these features have no discoverability path.
- **Severity:** Medium
- **Fix:** Add "Don't have an account? Register" and "Forgot your password?" links below the form.

### 17. Auth pages (Register, etc.) use Jetstream's default Tailwind-only components — style clash
- **File:** `resources/js/Pages/Auth/Register.vue`
- **Issue:** Register and other auth pages use Jetstream's `AuthenticationCard`, `TextInput`, `PrimaryButton` etc., which are plain Tailwind-styled components. These look completely different from the rest of the app which uses Vuetify dark theme. Users see a light-themed, vanilla-styled registration page → then a dark Vuetify dashboard.
- **Severity:** Medium
- **Fix:** Rebuild auth pages with Vuetify components to match the app's design system (like Login.vue already does).

### 18. Week view button exists but has no implementation
- **File:** `resources/js/Pages/Calendar/Index.vue`, lines ~207–213
- **Issue:** The calendar header has Month/Week toggle buttons. Clicking "Week" sets `viewMode = 'week'` but there is no week-view template — only the month grid is rendered (no `v-if` on viewMode).
- **Severity:** Medium
- **Fix:** Either implement the week view or remove the toggle button.

### 19. `v-html` usage in Jetstream pages — potential XSS
- **Files:**
  - `resources/js/Pages/PrivacyPolicy.vue`, line 20
  - `resources/js/Pages/TermsOfService.vue`, line 20
  - `resources/js/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue`, lines 148, 152
- **Issue:** `v-html` renders raw HTML and can be an XSS vector if the `policy`, `terms`, `qrCode`, or `setupKey` props contain user-controlled content. While these specific values come from the backend/Jetstream (markdown rendered server-side), it's still a risk if data sources change.
- **Severity:** Medium (low current risk, but defense-in-depth principle)
- **Fix:** Sanitize HTML server-side before sending, or use a sanitizer like DOMPurify.

### 20. No error handling on many `router.post`/`router.patch` calls
- **Files:** Throughout all pages (Settings.vue, Lists/Show.vue, Spaces/Show.vue, Dashboard.vue, etc.)
- **Issue:** Many Inertia router calls only have `onSuccess` callbacks with no `onError` handler. When server-side validation fails or network errors occur, users see no feedback. Some calls do have `onError` while others don't — inconsistent.
- **Severity:** Medium
- **Fix:** Add `onError` handlers that display error messages via the snackbar system.

### 21. No loading states for many operations
- **Files:** Various — e.g., `Workspaces/Settings.vue` label save, `Lists/Show.vue` task create, `Spaces/Show.vue` product drag-drop
- **Issue:** Many form submissions and server operations lack `:loading` or `:disabled` states on buttons. Users can double-click and submit duplicate requests.
- **Severity:** Medium
- **Fix:** Use Inertia's `form.processing` or local `loading` refs to disable buttons during async operations.

### 22. GanttChart tooltip follows cursor position with fixed offset — can go off-screen
- **File:** `resources/js/Components/Cpm/GanttChart.vue`, lines ~182–187
- **Issue:** The tooltip is positioned at `event.clientX + 10, event.clientY + 10` and teleported to `body`. On small screens or when hovering bars near the right/bottom edge, the tooltip can overflow off-screen. No boundary detection.
- **Severity:** Medium
- **Fix:** Use Vuetify's `v-tooltip` or add boundary detection.

### 23. GanttChart dependency removal via click — no confirmation
- **File:** `resources/js/Components/Cpm/GanttChart.vue`, line ~250
- **Issue:** Clicking a dependency line immediately emits `dependency-remove` with no confirmation. Accidental clicks on the SVG line (which has a 12px invisible hit area) will silently remove dependencies.
- **Severity:** Medium

### 24. Empty watcher with no body
- **File:** `resources/js/Pages/Lists/Show.vue`, line ~62
- **Issue:** `watch(() => props.parentTask, (newValue) => {}, { immediate: true });` — this watcher does absolutely nothing. It's dead code that adds a reactive subscription for no reason.
- **Severity:** Medium (code smell, minor perf)

### 25. Duplicate calendar implementations
- **File:** `resources/js/Pages/Lists/Show.vue` (lines 370-440) — calendar view within list page
- **File:** `resources/js/Pages/Calendar/Index.vue` — standalone calendar page
- **Issue:** Nearly identical calendar grid logic (day rendering, getItemsForDate, navigation) is duplicated across two files instead of extracted into a shared component.
- **Severity:** Medium (maintainability)

### 26. `formatDuration` defined differently in 4+ files
- **Files:** `Dashboard.vue`, `MainLayout.vue`, `TimeTracking/Index.vue`, `TaskDetailPanel.vue`, `GanttChart.vue`, `CpmSummary.vue`
- **Issue:** Each file has its own `formatDuration` with different signatures (some take seconds, some minutes, some hours). No shared utility. Inconsistent formatting across the app.
- **Severity:** Medium (maintainability, potential display bugs)
- **Fix:** Extract into a shared `utils/formatters.js` with clearly named functions.

### 27. TaskCard emits `complete` event but doesn't show completion state for tasks
- **File:** `resources/js/Components/Tasks/TaskCard.vue`
- **Issue:** The card component emits a `@complete` event, yet for parent tasks (not subtasks), this triggers a no-op. There's no visual distinction in the card between "this is completable" (subtask) and "this is not completable" (task), confusing users.
- **Severity:** Medium

### 28. CpmSummary division by zero risk
- **File:** `resources/js/Components/Cpm/CpmSummary.vue`, line ~101
- **Issue:** `(stats.completed / stats.total) * 100` — if `stats.total` is 0 (no subtasks), this produces `NaN` which is passed to `v-progress-linear` and the percentage display shows `NaN%`.
- **Severity:** Medium

### 29. Sprints/Show.vue drag between columns has no loading feedback
- **File:** `resources/js/Pages/Sprints/Show.vue`
- **Issue:** Dragging tasks between "Backlog" and "Sprint" columns makes a server call, but there's no loading indicator or optimistic update. If the request fails, the task visually remains in the new column but the server state is unchanged.
- **Severity:** Medium

---

## LOW Issues

### 30. Tailwind CSS + Vuetify dual CSS framework
- **Files:** All components use a mix of Tailwind utilities (`flex`, `items-center`, `text-gray-400`, `bg-[#1e1e1e]`) and Vuetify classes (`d-flex`, `align-center`, `text-grey`, `pa-4`)
- **Issue:** Two CSS frameworks increase bundle size and create naming confusion. Some components mix both in the same element. Hard to maintain consistency.
- **Severity:** Low
- **Fix:** Standardize on one system. Since Vuetify is the component library, prefer Vuetify's utility classes.

### 31. Hardcoded colors throughout components
- **Files:** `GanttChart.vue` (`bg-red-500`, `bg-blue-500`, `#2d2d30`, `#1e1e1e`), `CpmSummary.vue` (`bg-[#252526]`), `MainLayout.vue`, etc.
- **Issue:** Theme colors are hardcoded as hex values and Tailwind classes instead of using Vuetify's theme system. Dark mode colors are baked in — no ability to switch to light theme.
- **Severity:** Low

### 32. No Vite chunk splitting or lazy loading configuration
- **File:** `vite.config.js`
- **Issue:** No `build.rollupOptions.output.manualChunks` configuration. All code is bundled together. Large pages like Lists/Show.vue (~1200 lines) and TaskDetailPanel (~1500 lines) are included in the main bundle even when not needed.
- **Severity:** Low
- **Fix:** Add code splitting for routes and heavy components (GanttChart, Calendar).

### 33. `console.error` left in production code
- **File:** `resources/js/Pages/Lists/Show.vue`, line ~468 (`console.error('Error fetching CPM data:', error)`)
- **Issue:** Error is logged to console in production. Should use a proper error reporting service or be removed.
- **Severity:** Low

### 34. Inline styles used extensively in TaskDetailPanel
- **File:** `resources/js/Components/Tasks/TaskDetailPanel.vue`
- **Issue:** Many elements use inline `style="..."` attributes (e.g., `style="max-width: 200px;"`, `style="align-items: flex-start; padding-top: 10px;"`) instead of scoped CSS classes. Hard to maintain and override.
- **Severity:** Low

### 35. GanttChart uses Tailwind classes inside SVG `foreignObject`
- **File:** `resources/js/Components/Cpm/GanttChart.vue`, lines ~270-300
- **Issue:** `<foreignObject>` elements inside SVG use Tailwind classes (`flex`, `items-center`, `gap-2`, `truncate`). Browser support for CSS inside `foreignObject` varies, and some Tailwind utilities may not render correctly in SVG context.
- **Severity:** Low

### 36. No form validation on Space creation
- **File:** `resources/js/Layouts/MainLayout.vue`, create space dialog
- **Issue:** The create space form allows submission with an empty name. Only server-side validation catches it.
- **Severity:** Low

### 37. Magic numbers / hardcoded values
- **File:** `resources/js/Components/Cpm/GanttChart.vue` — `chartPadding = 200`, `rowHeight = 48`, `headerHeight = 50`
- **File:** `resources/js/Pages/Dashboard.vue` — `.slice(0, 10)` for recent tasks
- **Issue:** Magic numbers scattered throughout without named constants or configuration options.
- **Severity:** Low

### 38. `x-cloak` directive declared in CSS but Alpine.js is not used
- **File:** `resources/css/app.css`, lines 5–7
- **Issue:** `[x-cloak] { display: none; }` is a CSS rule for Alpine.js. This project uses Vue 3, not Alpine. Dead CSS rule.
- **Severity:** Low

### 39. GanttChart linking mode UX confusion
- **File:** `resources/js/Components/Cpm/GanttChart.vue`, lines ~218-225
- **Issue:** The "Add Dependency" button sets `linkingMode = true` but doesn't set `linkingSource`. The user must then click a row to set the source. However, the `startLinking` function also checks `linkingMode.value` and sets the source. If a user clicks "Add Dependency" then clicks a bar (not a row), the flow is handled by the bar click handler which does `linkingSource ? completeLinking : startLinking`. This two-path logic is inconsistent and confusing.
- **Severity:** Low

### 40. `@tailwindcss/forms` plugin active — conflicts with Vuetify form styles
- **File:** `tailwind.config.js`, line 24
- **Issue:** The Tailwind `forms` plugin resets all form element styles (inputs, selects, checkboxes). Vuetify components have their own styling. The Tailwind forms plugin can interfere with Vuetify's form component rendering, especially for components outside Vuetify's wrapper (e.g., `<input>` in GanttChart).
- **Severity:** Low
- **Fix:** Remove `@tailwindcss/forms` or scope it to specific classes only.

### 41. No `<Head>` title on several pages
- **File:** `resources/js/Pages/Workspaces/Show.vue` — has `<Head>` but generic
- **File:** `resources/js/Pages/Calendar/Index.vue` — title is just "Calendar"
- **Issue:** Some pages don't include contextual information in the `<Head>` title (e.g., workspace name, list name). This makes browser tabs hard to differentiate when multiple are open.
- **Severity:** Low

### 42. Responsive design gaps
- **Files:** `GanttChart.vue`, `TaskDetailPanel.vue`, `CpmSummary.vue`
- **Issue:** The Gantt chart has horizontal scrolling but the left column (task names) doesn't sticky-scroll. CpmSummary uses a 4-column CSS grid that doesn't collapse on mobile. TaskDetailPanel as a navigation drawer may not adapt well on very small screens.
- **Severity:** Low

### 43. No keyboard shortcut support
- **Files:** All pages
- **Issue:** No keyboard shortcuts for common actions (create task, open search, navigate between views, close panels). The search dialog is only accessible via mouse click.
- **Severity:** Low
- **Fix:** Add `@keydown` listeners for common shortcuts (e.g., `/` for search, `Esc` for close panel).

---

## Recommendations (Priority Order)

1. **Fix `isAdmin` bug** — immediate runtime error (#1)
2. **Fix `saveEntry` missing create path** — broken feature (#5)
3. **Implement board filters** — filters UI exists but does nothing (#3)
4. **Switch to Vuetify auto-import** — significant performance win (#4)
5. **Replace `window.showSnackbar`** with provide/inject or Pinia (#8–9)
6. **Standardize font family** across all systems (#10)
7. **Add `onError` handlers** to all Inertia router calls (#20)
8. **Replace native `confirm()`** with Vuetify dialogs (#12)
9. **Fix handleTaskComplete UX** — remove or explain the no-op (#2)
10. **Extract shared utilities** — formatDuration, calendar logic (#25–26)
