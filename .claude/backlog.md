# Fin UI backlog

v1 is functionally complete — every item of the previous backlog shipped and git holds them.
This backlog is the **UI modernisation and polish** pass: simpler navigation, one editable plan,
a transaction list that keeps its filters, one reporting hub, and a theme that belongs to this
product. The `/next-item` command implements the **first unchecked item** and nothing else.

Statuses: `[ ]` todo · `[x]` done · `[!]` blocked — a blocked item halts the loop and carries a
note beneath it explaining why.

`<!-- gate -->` on an item means: run the full `sail composer test` before committing it.

Conventions for every item:

- One commit on `main`, subject line only, at most 60 characters, taken verbatim from the
  item's `Commit:` line. The loop never pushes and never opens a pull request.
- Read `docs/prd.md` for the item's `Refs:` before building — §5 for UX principles and the §5.3
  glossary copy, §9 for screens and routes, §14 for empty and edge states.
- Follow sibling files for structure. Reuse what is there before writing anything new.
- **The numbers do not move.** No item changes a `Calculate*` or `Build*` result;
  `tests/Feature/GoldenDataset/*` is the tripwire and passes untouched at every item.
- Regenerate Wayfinder with `--with-form` after any route change, run `bun run build` after any
  page move, and commit both.

---

## P1 — Structure

- [x] **nav-reports-hub** — Plan vs actual, cash flow and forecast become one hub
  - Refs: UX-05, UX-09, §9 screens and routes
  - Build: `routes/web.php` — the three report URIs move under `years/{year}/reports/`
    (`reports/comparison`, `reports/cash-flow`, `reports/forecast`) keeping the names
    `comparison.index`, `cash-flow.index` and `forecast.index` untouched, plus a redirect from
    each old URI · the three controllers' `Inertia::render` strings follow their pages:
    `comparison/index.tsx` → `reports/comparison.tsx`, `cash-flow/index.tsx` →
    `reports/cash-flow.tsx`, `forecast/index.tsx` → `reports/forecast.tsx` · new
    `resources/js/pages/reports/layout.tsx`, copied from `resources/js/pages/plan/layout.tsx` —
    the year, the tab row, `SelectedYearSetupBanner` · `wayfinder:generate --with-form`
  - Notes: nothing merges. `ComparisonController`, `CashFlowController` and `ForecastController`
    each keep exactly one `index` method and their props are unchanged. The Summary tab is added
    later by `reports-summary`; the tab row lists three tabs until then.
  - Tests: `tests/Feature/Reports/{ComparisonTest,CashFlowTest,ForecastTest}.php` — the new URIs
    and each redirect · path assertions updated in
    `tests/Browser/{ForecastTest,NavigationTest,AccessibilityTest,EmptyStatesTest,AcceptanceWalkTest}.php`
  - Commit: `feat: reports hub`

- [x] **nav-goals-hub** — Goals, emergency fund and net worth become one hub
  - Refs: GOAL-01, EF-01, NW-01, §9
  - Build: `routes/web.php` — `net-worth` → `goals/net-worth`, keeping the name
    `net-worth.index`, with a redirect from the old URI · `resources/js/pages/net-worth/index.tsx`
    → `resources/js/pages/goals/net-worth.tsx` and `NetWorthController`'s render string with it ·
    new `resources/js/pages/goals/layout.tsx` with the three tabs · `wayfinder:generate --with-form`
  - Notes: only the GET moves. The `net-worth/items` write routes are POST/PATCH/DELETE, never
    bookmarked, and stay where they are under the name `net-worth-items.*`.
  - Tests: `tests/Feature/NetWorth/NetWorthControllerTest.php` — new URI and redirect ·
    `tests/Browser/{NetWorthTest,GoalsTest,NavigationTest}.php`
  - Commit: `feat: goals hub`

- [x] **nav-plan-subscriptions** — Subscriptions become a tab of the plan
  - Refs: SUB-01, SUB-02, SUB-04, SUB-06, §9
  - Build: `SubscriptionController@index` renders `plan/subscriptions` instead of
    `subscriptions/index` — URI and route name unchanged · page moves to
    `resources/js/pages/plan/subscriptions.tsx` and wraps in `resources/js/pages/plan/layout.tsx` ·
    `plan/layout.tsx` gains the Subscriptions tab, built from the shared `selectedYear` prop
    rather than the `tabs` prop, because this route is not year-scoped
  - Notes: `PlanController::TABS` does not change — the tab row gains one fixed entry the layout
    owns. Subscriptions are user-level: `SyncSubscriptionPlanItems` fans them into every current
    and future year, and the page says so once so the tab's place under a year is not misread.
  - Tests: `tests/Feature/Subscriptions/SubscriptionControllerTest.php` ·
    `tests/Browser/SubscriptionsTest.php` at both widths
  - Commit: `feat: subscriptions in the plan`

- [x] **nav-sidebar-groups** — Twelve sidebar entries become seven <!-- gate -->
  - Refs: UX-05, UX-09, UX-10, NFR-06, §9 navigation
  - Build: `resources/js/hooks/use-app-navigation.ts` — Dashboard, Transactions, Plan, Months,
    Reports, Goals, then Settings; year-scoped entries still hidden while `selectedYear` is null ·
    `resources/js/components/nav-main.tsx` — drop the single "Your money" group label and set
    Settings off with a separator · active state through `isCurrentOrParentUrl` from
    `resources/js/hooks/use-current-url.ts`, so a tab inside a hub lights its sidebar parent ·
    breadcrumbs on every moved page
  - Tests: `tests/Browser/NavigationTest.php` — seven entries and no more, every one reachable at
    1280px and 375px, each hub tab lighting its parent
  - Commit: `feat: seven entry navigation`

---

## P2 — Identity

- [x] **ui-design-tokens** — A palette and a type scale of its own
  - Refs: §5.2, UX-08, NFR-04, NFR-06
  - Build: `resources/css/app.css` — `--primary` becomes indigo (`oklch(0.45 0.15 265)` light,
    `oklch(0.72 0.14 265)` dark) instead of pure black; the neutrals pick up a faint cool cast at
    hue 265; **`--card` must stop equalling `--background` in dark** (`oklch(0.205 0.008 265)` on
    `oklch(0.16 0.008 265)`) so cards lift; `--radius` 0.625rem → 0.5rem; new `--shadow-card`;
    `--chart-1..5`, defined and never used today, become a real categorical ramp off hue 265 ·
    `--font-display` mapped in `@theme` and used for page titles and hero figures ·
    `resources/views/app.blade.php` — add Instrument Serif beside Instrument Sans on
    fonts.bunny.net (no npm dependency) and keep the inline pre-hydration background rule in step
    with the new `--background` · sweep raw Tailwind colour utilities onto the semantic tokens in
    `components/transactions/{transaction-row,issue-badge,quick-add-sheet}.tsx`,
    `components/finance/month-status-badge.tsx`, `components/input-error.tsx`,
    `pages/months/index.tsx`, `pages/plan/expenses.tsx`
  - Notes: `--status-ok|warning|over` keep their hues (155, 70, 27) so they stay clear of the
    brand; `--series-forecast` moves 258 → 265 to sit in the brand family. Contrast stays at
    WCAG AA in both themes. `welcome.tsx` is left to `ui-landing`.
  - Tests: new `tests/Unit/DesignTokensTest.php` — no file under `resources/js` matches a raw
    Tailwind colour utility (`amber-`, `emerald-`, `red-`, `green-`, `sky-`, and friends) ·
    `tests/Browser/AccessibilityTest.php` extended — in dark mode a card and the page background
    render as different colours, and every status badge still carries its icon and text label
  - Commit: `feat: design tokens and type scale`

- [x] **ui-primitives** — One page rhythm, one figure card, and the dead code goes
  - Refs: UX-05, UX-09, UX-13
  - Build: `resources/js/components/page-shell.tsx` — one page gutter
    (`px-4 md:px-6 lg:px-8`) and one section rhythm (`space-y-8`), applied to every page that
    currently picks its own `px-4 py-6` · `components/finance/stat-card.tsx`, generalised from
    `metric-card.tsx`, so the dashboard, month review, cash flow, forecast and net worth stop each
    writing their own `<dl>` of figures · replace the hand-rolled `Pager` in
    `pages/transactions/index.tsx` with `components/ui/pagination`, which is vendored and unused ·
    delete dead code: `components/app-header.tsx`, `layouts/app/app-header-layout.tsx`,
    `layouts/auth/auth-card-layout.tsx`, `layouts/auth/auth-split-layout.tsx`,
    `components/ui/{icon,placeholder-pattern,toggle}.tsx`
  - Notes: `toggle-group` stays — the comparison mode switch uses it. Deleting a file something
    still imports fails `tsc --noEmit`, which is the check that this list is right.
  - Tests: `tests/Browser/{DashboardTest,TransactionsTest}.php` — pagination works at both widths
  - Commit: `feat: shared page primitives`

- [x] **ui-landing** — The landing page stops being a starter kit <!-- gate -->
  - Refs: §1, NFR-04
  - Build: rewrite `resources/js/pages/welcome.tsx` — 388 lines of Laravel starter kit with about
    twenty hardcoded hex values and a large inline SVG — on the new tokens: what Fin is, one
    "Sign in" action, nothing implying public registration exists
  - Notes: `/register` does not exist and must never be referenced; a link to it breaks the
    Wayfinder build.
  - Tests: `tests/Browser/WelcomeTest.php` extended — renders at 1280px and 375px with no console
    errors and no registration link
  - Commit: `feat: landing page`

---

## P3 — Easier plan setup

- [x] **plan-item-editor** — The plan tabs stop being read-only
  - Refs: INC-01, INC-07, IRR-01, IRR-02, IRR-03, BUD-01, UX-06, UX-07
  - Build: `resources/js/components/planning/plan-item-form.tsx` — name, category, amount and
    frequency in front, start month, payment day, allocation and notes folded behind "More
    details" (UX-07) · `pages/plan/income.tsx` and `pages/plan/irregular.tsx` gain inline create,
    edit and delete through the existing `plan-items.store/update/destroy`, following the
    inline-form pattern already proven in `pages/subscriptions/index.tsx` and
    `pages/goals/index.tsx` · delete confirms first (UX-06) using `components/ui/dialog`
  - Notes: no controller, Action or Form Request changes — `PlanItemController` and
    `Store/UpdatePlanItemRequest` already do all of this and only the wizard ever reached them.
    Salary-model and subscription-sourced items stay read-only, which `isManual` already reports.
  - Tests: `tests/Feature/Plan/PlanItemControllerTest.php` extended for the tabs' payloads ·
    `tests/Browser/PlanningTest.php` — add, edit and delete an income item and an irregular item
    from the tabs at 1280px and 375px
  - Commit: `feat: edit plan items in place`

- [x] **plan-budget-grid** — The grid says what it saved
  - Refs: BUD-02, BUD-03, BUD-04, UX-13, EDGE-03
  - Build: `pages/plan/expenses.tsx` — the grid PATCHes `budget-cell.update` on every blur today
    with no saved state, no error surface and no undo. Add a per-cell saving and saved indicator,
    surface the `amount` error `BudgetCellController` already returns (including the ambiguous
    "more than one manual item" path), arrow-key movement between cells, and an undo on the last
    write · the mobile one-month-at-a-time view gets the same treatment
  - Notes: `UpdateBudgetCell` already creates a plan item named after the category on first entry
    and already reports `isEditable`. Do not add an endpoint and do not change the Action.
  - Tests: `tests/Feature/Plan/BudgetGridTest.php` extended for the error path ·
    `tests/Browser/PlanningTest.php` — type a cell, see it save, see an error surface, at both widths
  - Commit: `feat: budget grid feedback`

- [x] **plan-wizard-short** — Six setup steps become three
  - Refs: YEAR-04, YEAR-05, OPEN-01, OPEN-02, OPEN-03, INC-01, BUD-01, UX-06, UX-07
  - Build: `app/Http/Controllers/YearSetupController.php` — `STEPS` becomes
    `['opening','income','expenses']`; the placeholder `goals` step and the `irregular` and
    `review` steps go, and `YearSetupCompletionController` still runs `CompleteYearSetup` and
    `CapturePlanBaseline` at the hand-off to `plan.show` · one shared class takes over
    `openingProps()` and `presentSalaryModel()`, which `YearSetupController` and `PlanController`
    copy from each other today · `resources/js/pages/years/setup.tsx` drops from 619 lines to the
    three remaining steps and reuses `plan-item-form.tsx` and the opening form rather than
    carrying its own near-verbatim copies
  - Notes: the baseline must come out identical to what six steps produced —
    `CapturePlanBaseline::build()` reads plan items, not the wizard, so shortening the wizard
    cannot move it, and `tests/Feature/GoldenDataset/*` proves it.
  - Tests: `tests/Feature/Years/YearSetupControllerTest.php` — three steps, an unknown step 404s,
    completion still captures the baseline · `tests/Browser/PlanningTest.php` — the wizard end to
    end at 375px
  - Commit: `feat: three step year setup`

- [x] **plan-overview** — The plan's totals are always in view <!-- gate -->
  - Refs: UX-05, UX-09, FC-06, EDGE-02
  - Build: an annual totals rail at the top of every plan tab in `resources/js/pages/plan/layout.tsx`
    — planned income, planned expenses, planned savings, planned year-end balance — fed by the
    `summary` prop `CapturePlanBaseline::build()` already returns, with whichever tab is still
    empty named as the next thing to do
  - Notes: `summary` is passed only on the income tab today; `PlanController::show()` moves it
    into the shared props so every tab has it. That is a prop move, not a new calculation.
  - Tests: `tests/Feature/Plan/PlanControllerTest.php` — `summary` present on all four tabs ·
    `tests/Browser/PlanningTest.php`
  - Commit: `feat: plan overview rail`

---

## P4 — Easier transactions

- [x] **tx-filter-bar** — Filters survive the next page
  - Refs: TXL-02, TXL-03, EDGE-01, UX-07, UX-09
  - Build: `components/transactions/transaction-filters.tsx` — 298 lines of always-open controls
    become a search field, the filters actually reached for as chips, and the rest behind a "More
    filters" popover carrying a count of what is set · `pages/transactions/index.tsx` — **the
    `Pager` carries only `q`, `type`, `category_id` and `issues` forward, so changing page
    silently drops the date, month, year, subcategory, account and amount filters.** Build the
    page link from the whole `filters` prop instead
  - Notes: `IndexTransactionRequest` coerces and never validates, so no filter combination can
    422 and a redesign here cannot break the request.
  - Tests: `tests/Feature/Transactions/TransactionListTest.php` — every filter survives a page
    change, and the footer totals still cover the whole filter rather than the page ·
    `tests/Browser/TransactionsTest.php` at both widths
  - Commit: `fix: keep every filter when paging`

- [x] **tx-list-polish** — The list reads and moves better
  - Refs: TXL-01, TXL-04, TXL-05, UX-05, UX-13, NFR-06
  - Build: `pages/transactions/index.tsx` — totals move above the list and stick while it scrolls
    (they are below the fold today, which is backwards for UX-05), rows take keyboard navigation
    with Enter to edit, and the selection count says plainly that it covers this page only ·
    `components/transactions/transaction-row.tsx` onto the new tokens
  - Tests: `tests/Browser/{TransactionsTest,BulkRecategoriseTest}.php`
  - Commit: `feat: transaction list polish`

- [x] **tx-quick-add-polish** — Quick add gets out of its own way
  - Refs: TXQ-01, TXQ-02, TXQ-03, TXQ-04, TXQ-06, TXQ-10, UX-01, UX-11, UX-12, UX-14, §2.3
  - Build: split `components/transactions/quick-add-sheet.tsx` — 496 lines serving create, edit
    and duplicate — into the sheet shell plus `quick-add-form.tsx` and `quick-add-date.tsx` ·
    a Today / Yesterday / pick control replacing the bare `<input type="date">` · larger amount
    and category targets below 768px
  - Notes: `entry_source` and `entry_duration_ms` must keep being sent — they are how §2.3's
    fifteen-second target is measured. The Radix Popover inside the Dialog still needs `modal`.
  - Tests: `tests/Browser/QuickAddTest.php` — the common path still lands in six interactions or
    fewer at both widths, save-and-add-another, the `12,50` comma decimal, and the complete-month
    reopen-and-save path
  - Commit: `feat: quick add polish`

- [x] **tx-command-palette** — One key reaches anything <!-- gate -->
  - Refs: UX-05, NFR-06, §9 navigation
  - Build: `resources/js/components/command-palette.tsx` on the already-vendored `cmdk`
    (`components/ui/command`), mounted beside `QuickAddProvider` in
    `layouts/app/app-sidebar-layout.tsx` — ⌘K and Ctrl+K jump to any of the seven destinations
    and their tabs, switch year from the shared `years` prop, and start a transaction through
    `useQuickAdd()`
  - Notes: the shortcut is ignored while an input is focused, reusing `isTypingInto()` from
    `hooks/use-quick-add.ts`. `N` keeps working exactly as it does.
  - Tests: `tests/Browser/CommandPaletteTest.php` — open with the shortcut, navigate, switch
    year, start a transaction, at 1280px and 375px
  - Commit: `feat: command palette`

---

## P5 — Easier reporting

- [x] **reports-summary** — The year in one screen
  - Refs: FC-01, CF-01, ALRT-01, ALRT-06, UX-05, UX-09
  - Build: `app/Http/Controllers/ReportSummaryController.php@index` at `years/{year}/reports`
    (name `reports.index`), composing `CalculateAnnualSummary`, `CalculateCashFlow` and
    `BuildAlerts` — **no new calculation code and no new Action** ·
    `resources/js/pages/reports/summary.tsx`, linking into each of the other three tabs ·
    Summary joins `reports/layout.tsx` as the first tab and becomes the Reports sidebar destination
  - Tests: `tests/Feature/Reports/ReportSummaryTest.php` · `tests/Feature/GoldenDataset/*`
    untouched and green · `tests/Browser/NavigationTest.php`
  - Commit: `feat: reports summary tab`

- [x] **reports-charts** — Plan vs actual and forecast get a picture
  - Refs: CMP-01, CMP-03, CMP-04, FC-02, FC-05, §5.2, UX-08
  - Build: a plan-against-actual bar chart on `pages/reports/comparison.tsx` and a forecast line
    chart on `pages/reports/forecast.tsx` — both on recharts through `components/ui/chart.tsx`,
    both on the `--series-*` tokens with the §5.2 line styles, both wrapped in
    `components/finance/chart-data-table.tsx` so the table twin stays one click away ·
    `aria-label` on each
  - Notes: `components/finance/balance-chart.tsx` is the model for dash patterns and the zero
    `ReferenceLine`. No new props — both pages already receive everything the charts need, so no
    controller changes.
  - Tests: `tests/Browser/{ForecastTest,NavigationTest}.php` — chart and table views both render
    with no console errors at both widths
  - Commit: `feat: charts on comparison and forecast`

- [x] **dashboard-simplify** — Six cards, two charts, then drill down <!-- gate -->
  - Refs: DASH-01, DASH-02, DASH-03, DASH-04, UX-05, UX-09
  - Build: `pages/dashboard.tsx` — six cards above the fold and no more (UX-09), the secondary
    pill row folded into them, and of the five deferred charts only the two that answer "how is
    the year going" left above the fold; the rest move behind a disclosure and into the Reports
    hub · `components/finance/metric-card.tsx` onto `stat-card.tsx`
  - Notes: the five `Build*Chart` Actions and their tests stay, and so does every
    `Inertia::defer` prop — a chart behind a disclosure keeps its deferred prop and its coverage.
    Nothing under `app/` is deleted by this item.
  - Tests: `tests/Feature/Dashboard/{DashboardControllerTest,DashboardChartsTest}.php` unchanged
    and green · `tests/Browser/DashboardTest.php` — at most six cards above the fold at 1280px
  - Commit: `feat: simpler dashboard`

---

## P6 — Close

- [x] **ui-states** — Nothing is ever blank without saying why
  - Refs: EDGE-01, EDGE-02, EDGE-03, EDGE-04, EDGE-05, UX-06, UX-07, NFR-04
  - Build: `components/finance/empty-state.tsx` applied with exactly one next action on every
    list and chart · a skeleton at every `<Deferred>` boundary shaped like what it replaces
    rather than a generic bar · error surfaces on the forms that show none today (the budget grid
    is already covered by `plan-budget-grid`; this catches the rest) · "—" for every null
    percentage
  - Tests: `tests/Feature/EdgeStatesTest.php` · `tests/Browser/EmptyStatesTest.php`
  - Commit: `feat: loading and empty states`

- [x] **ui-final-pass** — Both widths, every screen, keyboard throughout <!-- gate -->
  - Refs: UX-10, UX-13, NFR-04, NFR-06, TST-04, TST-05, §2.2
  - Build: a sweep of every screen this backlog touched at 1280px and 375px — keyboard reach,
    visible focus, labelled inputs, no horizontal scroll, tables over four columns as card lists
    (the budget grid excepted) · `tests/Browser/AcceptanceWalkTest.php` rewalks §2.2 over the
    new navigation
  - Tests: every authenticated page visited at both widths with `assertNoJavascriptErrors()` and
    no console errors
  - Commit: `test: full ui browser coverage`

---

## Known gaps

Nothing outstanding. A blocked item records its reason here.

## Deferred — SHOULD items

Not scheduled. The loop stops before these; the user decides whether to pick them up.

- TXQ-05 — description autocomplete filling category, subcategory and account
- TXQ-09 — non-blocking "Possible duplicate" hint
- MON-07 — reconciliation hint when recorded cash differs from C_A(m)
- SUB-07 — widget listing subscriptions charging in the next 30 days
- MET-02 — `docs/metrics.md` with read-only SQL for each brief metric
- TST-06 — the NFR-01 performance benchmark in its own Pest group, excluded from the default run
- CSV export of the transaction list and of any report
- Cross-year reporting (every report route is year-scoped today)
