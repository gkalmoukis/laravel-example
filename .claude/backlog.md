# Fin v1 backlog

Ordered work remaining to finish `docs/prd.md` v1. The `/next-item` command implements the
**first unchecked item** and nothing else.

Statuses: `[ ]` todo · `[x]` done · `[!]` blocked — a blocked item halts the loop and carries a
note beneath it explaining why.

`<!-- gate -->` on an item means: run the full `sail composer test` before committing it.

Conventions for every item:

- One commit, subject line only, taken verbatim from the item's `Commit:` line.
- Read `docs/prd.md` for the item's `Refs:` before building. §7 is normative.
- Follow sibling files for structure. Generate with `sail artisan make:*`.
- Regenerate Wayfinder with `--with-form` after any route change, and commit the output.

---

## M3 — Transactions (finish)

- [x] **m3-app-shell-nav** — Navigation, the year switcher and the selected year
  - Refs: YEAR-07, YEAR-08, EDGE-02, UX-05, §9 navigation
  - Build: `app/Http/Middleware/HandleInertiaRequests.php` — share `years` (the user's financial
    years, newest first) and `selectedYear`, resolved route param → `?year=` → session →
    current calendar year → latest · new `resources/js/components/finance/year-switcher.tsx`
    reusing `components/planning/year-nav.tsx` where it fits ·
    `resources/js/components/app-sidebar.tsx` and `app-header.tsx` — nav entries for routes that
    **already exist** (Dashboard, Plan, Settings); later items append their own ·
    `resources/js/components/finance/setup-banner.tsx` ("Finish setting up {year}", EDGE-02) ·
    `resources/js/types/global.d.ts` sharedPageProps
  - Tests: extend `tests/Unit/Middleware/HandleInertiaRequestsTest.php` (every resolution arm) ·
    new `tests/Browser/NavigationTest.php` at 1280px and 375px
  - Commit: `feat: app navigation and year switcher`

- [x] **m3-transaction-write-actions** — Recording, editing and deleting a transaction
  - Refs: TXV-01, TXV-02, TXV-03, TXV-05, TXF-03, MET-01, CAT-03, CAT-04, USR-02, USR-03
  - Build: `app/Actions/{CreateTransaction,UpdateTransaction,DeleteTransaction,ReopenMonth}.php` ·
    `app/Policies/TransactionPolicy.php` (other users' records 404 via
    `Response::denyAsNotFound()`) · `app/Http/Requests/{Store,Update}TransactionRequest.php`
    (amount parsed in `prepareForValidation` via `Money::fromInput`, mirroring
    `StorePlanItemRequest`; `reopen_month` boolean for TXV-02) ·
    `app/Http/Controllers/TransactionController.php` (store, update, destroy only for now) ·
    **fix `App\Models\Category::isInUse()`** — it checks only `children()`, so CAT-03/04 do not
    hold; add the plan-item and transaction clauses · routes
    `transactions.store/update/destroy`
  - Notes: TXV-02 — a write into a Complete month is rejected unless `reopen_month` is set, in
    which case reopening and the write happen in one `DB::transaction()`. The refusal carries
    `month_complete` (the month number) beside the `occurred_on` message, which is what lets the
    form offer "Reopen {month} and save".
    CAT-03/CAT-04 were already satisfied — `type` is absent from `UpdateCategoryRequest` so it can
    never change, and `destroy` is deactivation, so categories are never deleted. What this item
    added was `isInUse()`'s plan-item and transaction clauses, which its own docblock deferred here.
  - Tests: `tests/Feature/Transactions/TransactionControllerTest.php` ·
    `tests/Feature/Transactions/CompleteMonthGuardTest.php` ·
    `tests/Feature/Isolation/TransactionIsolationTest.php` · extend
    `tests/Feature/Domain/CategoryControllerTest.php` for the new in-use clauses
  - Commit: `feat: transaction write actions`

- [x] **m3-transaction-list-and-filters** — The transaction list, filters and totals
  - Refs: TXL-01, TXL-02, TXL-03, TXL-05, TXV-05, EDGE-01, EDGE-04
  - Build: `TransactionController@index` — newest first, 50 per page, `Transaction::withIssues()`,
    filter state from the query string, footer totals from a **grouped aggregate over the whole
    filter**, not the paginated page · `app/Http/Requests/IndexTransactionRequest.php` ·
    `sail bunx shadcn@latest add pagination` · `resources/js/pages/transactions/index.tsx` ·
    `resources/js/components/transactions/{transaction-row,transaction-filters,issue-badge}.tsx` ·
    card list below 768px (UX-13) · route `transactions.index` · sidebar entry
  - Notes: Essentials strict mode throws on unselected columns — do not narrow the `select()`.
  - Tests: `tests/Feature/Transactions/TransactionListTest.php` (each filter, pagination,
    totals over the filter, issue badges, future-date badge, empty state)
  - Commit: `feat: transaction list and filters`

- [x] **m3-quick-add-dialog** — Quick add on every page
  - Refs: TXQ-01, TXQ-02, TXQ-03, TXQ-04, TXQ-06, TXQ-07, TXQ-08, TXQ-10, UX-11, UX-12, UX-14, ACC-02
  - Build: `sail bunx shadcn@latest add command popover` (cmdk, approved by PRD §3.3; popover is
    what the combobox needs) · `resources/js/components/transactions/quick-add-sheet.tsx`
    (Dialog ≥768px, bottom Sheet below) · `category-combobox.tsx` — active categories of the
    chosen type, the 5 most used in the last 90 days first then alphabetical, typing a
    subcategory name fills both fields · `new-transaction-button.tsx` — top bar on desktop,
    56px floating button bottom-right on mobile labelled "New transaction" for screen readers ·
    `resources/js/hooks/use-quick-add.ts` — `N` shortcut ignored while an input is focused,
    measures open→save into `entry_duration_ms` · share `quickAdd` as `Inertia::optional()` from
    `HandleInertiaRequests` (categories, subcategories, accounts, most-used, default account,
    years), fetched with `router.reload({ only: ['quickAdd'] })` when the sheet opens · mount in
    `resources/js/layouts/app/app-sidebar-layout.tsx`
  - Notes: ACC-02 "most recently used" = most recently entered (`created_at`). TXQ-08 warns
    inline when the date falls in a year with no plan but still saves. TXQ-07 toast then partial
    reload. Amount input `inputmode="decimal"`.
  - Tests: `tests/Feature/Transactions/QuickAddOptionsTest.php` (ordering, most-used window,
    default account, the optional prop resolving)
  - Commit: `feat: quick add transactions`

- [x] **m3-transaction-edit-duplicate-delete** — Editing, duplicating and deleting from the list
  - Refs: TXF-01, TXF-02, TXF-03, TXV-02, CMP-02
  - Build: reuse `quick-add-sheet.tsx` in edit and duplicate modes (duplicate prefills the
    original's fields with **today's** date and saves with `entry_source = Duplicate`) ·
    `resources/js/components/transactions/delete-transaction-dialog.tsx` — copy exactly
    "Delete this 12,50 € expense from 3 March?" · `reopen-and-save-alert.tsx` surfacing the
    TXV-02 rejection with a "Reopen {month} and save" action
  - Tests: extend `tests/Feature/Transactions/TransactionControllerTest.php`
  - Commit: `feat: edit, duplicate and delete transactions`

- [x] **m3-bulk-recategorise** — Changing the category of many transactions at once
  - Refs: TXL-04
  - Build: `app/Actions/RecategorizeTransactions.php` returning updated and skipped counts with
    reasons · `app/Http/Requests/UpdateTransactionCategoryRequest.php` ·
    `app/Http/Controllers/TransactionCategoryController.php@update` · route
    `transaction-category.update` · `resources/js/components/transactions/bulk-actions-bar.tsx`
    (checkbox per row plus select-all-on-this-page)
  - Notes: rows in a Complete month are **skipped**, not reopened — report
    "Updated 12, skipped 3 in completed months". Type incompatibility is also a skip reason.
  - Tests: `tests/Feature/Transactions/RecategorizeTransactionsTest.php`
  - Commit: `feat: bulk recategorise transactions`

- [x] **m3-transaction-browser-tests** — Browser coverage for the fifteen-second path <!-- gate -->
  - Refs: TST-04 (quick add slice), TST-05, UX-10, NFR-06
  - Build: `tests/Browser/TransactionsTest.php`
  - Tests: common path in ≤6 interactions (open, amount, pick category, description, save) ·
    Save & add another · `12,50` comma decimal · Complete-month guard then reopen-and-save ·
    bulk recategorise · each at 1280px and 375px with `assertNoJavascriptErrors()`
  - Commit: `test: transaction browser coverage`

## M4 — Actuals, months, comparison, cash flow

- [x] **m4-monthly-figures** — Plan, Actual and Forecast per category and month
  - Refs: §7.1, MON-01, TXV-05, NFR-02, NFR-03
  - Build: `app/Data/{MonthlyFigures,CategoryFigures,MonthTotals}.php` (final readonly) ·
    `app/Actions/CalculateMonthlyFigures.php` — one grouped aggregate per series over
    `Transaction::valid()` and `plan_item_amounts`, subcategories rolling up into parents,
    **`today` injected as a `CarbonImmutable` parameter, never `now()`** ·
    `App\Models\User::today()` is the resolver, already built ·
    extend `tests/Unit/ArchTest.php` — `App\Data` is final and readonly, and no
    `float`/`(float)`/`round(` in `app/Actions` or `app/ValueObjects` except
    `Money::multiplyByRatio`
  - Notes: §7.1 has no dependency on the current date, so `CalculateMonthlyFigures` takes
    no `today`. The convention still holds for the Actions that do need it — `today` is a
    `CarbonImmutable` argument, never `now()` inside, resolved by `User::today()`.
    Status(m) per §7.1; F(c,m) = A when Complete, else max(P, A).
  - Tests: `tests/Unit/Actions/CalculateMonthlyFiguresTest.php` — hand-computed expectations
    including P=0, the InProgress max rule, 31 Dec / 1 Jan boundaries, and Athens vs UTC at
    midnight
  - Commit: `feat: monthly plan, actual and forecast figures`

- [x] **m4-cash-flow-and-annual-summary** — Balances and the annual totals
  - Refs: §7.2, §7.3, EDGE-03
  - Build: `app/Data/{CashFlow,CashFlowMonth,AnnualSummary}.php` ·
    `app/Actions/CalculateCashFlow.php` — B₀ from month-0 Cash + EmergencyFund snapshots,
    C_P/C_F/C_A series, m_last rule, current available balance ·
    `app/Actions/CalculateAnnualSummary.php` — planned/actual/forecast income, expenses,
    savings; savings rate `null` when income is 0; deviation against
    `financial_years.baseline`, falling back to C_P(12) labelled "vs current plan"
  - Notes: derived figures that can go negative stay signed `int` cents, never `Money`.
    m_last for a past year with no recorded months, or a future year → no Actual series.
  - Tests: `tests/Unit/Actions/CalculateCashFlowTest.php`,
    `tests/Unit/Actions/CalculateAnnualSummaryTest.php`
  - Commit: `feat: cash flow and annual summary figures`

- [x] **m4-variance-calculation** — Variance, thresholds and severity ordering
  - Refs: §7.4, CMP-05, CMP-06
  - Build: `app/Data/{Variance,VarianceReport}.php` · `app/Actions/CalculateVariances.php` —
    uses `budget_warning_threshold_percent`, the expense and income tables, `NoPlan` when
    P=0 and A=0, spread-only categories evaluated year-to-date cumulative ·
    `severity(): int` on `app/Enums/VarianceStatus.php`
  - Notes: exactly-100% coverage means every `match` arm needs a test.
  - Tests: `tests/Unit/Actions/CalculateVariancesTest.php`
  - Commit: `feat: budget variance calculation`

- [x] **m4-months-index** — Twelve month cards
  - Refs: MON-01, MON-02, EDGE-01
  - Build: `app/Http/Controllers/MonthController.php@index` · route `months.index`
    (`GET /years/{year}/months`) · `resources/js/pages/months/index.tsx` ·
    `resources/js/components/finance/month-status-badge.tsx` · sidebar entry
  - Tests: `tests/Feature/Months/MonthIndexTest.php`
  - Commit: `feat: month status overview`

- [x] **m4-month-review** — The month review screen
  - Refs: MON-03 (steps 1–3), CMP-01, CMP-02, EDGE-01, UX-08
  - Build: `MonthController@show` (404 when `{month}` is outside 1–12) · route `months.show` ·
    `resources/js/pages/months/show.tsx` ·
    `resources/js/components/finance/{variance-row,series-legend}.tsx` ·
    `--series-plan`/`--series-actual`/`--series-forecast` and
    `--status-ok`/`--status-warning`/`--status-over` tokens in `resources/css/app.css`, each
    paired with an icon and a text label · every actual amount links into `/transactions?…`
  - Tests: `tests/Feature/Months/MonthReviewTest.php`
  - Commit: `feat: month review screen`

- [x] **m4-month-close** — Completing and reopening a month
  - Refs: MON-03 (step 5), MON-04, MON-05
  - Build: `app/Actions/CompleteMonth.php` — blocked while the month has flagged transactions,
    blocked when the month starts after today in the user's timezone, confirmation required to
    complete the current month before its last day · `app/Http/Controllers/MonthCompletionController.php`
    (store = complete, destroy = reopen, reusing `ReopenMonth`) · routes
    `month-completion.store/destroy` · `resources/js/components/months/complete-month-card.tsx`
  - Tests: `tests/Feature/Months/MonthCompletionTest.php`
  - Commit: `feat: complete and reopen a month`

- [x] **m4-net-worth-snapshot-entry** — Recording what you have at month end
  - Refs: MON-06, NW-03
  - Build: `app/Actions/SaveNetWorthSnapshots.php` ·
    `app/Http/Requests/UpdateNetWorthSnapshotRequest.php` ·
    `app/Http/Controllers/NetWorthSnapshotController.php@update` · route
    `net-worth-snapshots.update` · `resources/js/components/net-worth/snapshot-form.tsx` —
    all active items prefilled with the previous month's value (month 0 for January), liquid
    items showing C_A(m) as a hint; saving is optional for completion
  - Tests: `tests/Feature/Months/NetWorthSnapshotTest.php`
  - Commit: `feat: month end net worth snapshots`

- [x] **m4-comparison-screen** — Plan vs Actual
  - Refs: CMP-03, CMP-04, CMP-05, CMP-06, CAT-08
  - Build: `app/Http/Controllers/ComparisonController.php@index` · route `comparison.index` ·
    `resources/js/pages/comparison/index.tsx` — month mode (default: latest Complete month,
    else current month, else month 1) and Year-to-date mode with "Based on {n} completed
    months"; separate income and expense sections; rows expand to subcategories plus "No
    subcategory"; spread categories carry the explanatory tooltip · sidebar entry
  - Tests: `tests/Feature/Reports/ComparisonTest.php`
  - Commit: `feat: plan vs actual comparison`

- [x] **m4-cash-flow-screen** — The twelve-month cash-flow table and balance chart
  - Refs: CF-01, CF-02, NFR-04
  - Build: `sail bunx shadcn@latest add chart` (recharts, approved by PRD §3.3) ·
    `app/Http/Controllers/CashFlowController.php@index` · route `cash-flow.index` ·
    `resources/js/pages/cash-flow/index.tsx` ·
    `resources/js/components/finance/balance-chart.tsx` — three closing-balance series in the
    §5.2 line styles with a zero line and a descriptive `aria-label` ·
    `resources/js/components/finance/chart-data-table.tsx` — the shared "View as table"
    alternative every later chart reuses · sidebar entry
  - Tests: `tests/Feature/Reports/CashFlowTest.php`
  - Commit: `feat: cash flow table and balance chart`

- [x] **m4-golden-dataset-part-1** — The fixture year, actuals half
  - Refs: TST-01, TST-02 (part)
  - Build: `tests/Fixtures/GoldenYear.php` — a synthetic fully-specified year (Q-07 default)
    with hand-computed expectations
  - Tests: `tests/Feature/GoldenDataset/ActualsAndBalancesTest.php` — monthly figures, the
    twelve-month cash-flow table and every variance
  - Commit: `test: golden dataset for actuals and balances`

- [ ] **m4-month-close-browser-test** — Browser coverage for the close flow <!-- gate -->
  - Refs: TST-04 (month close slice), UX-10
  - Build: `tests/Browser/MonthsTest.php`
  - Tests: months index → review → fix a flagged transaction → save snapshots → complete →
    reopen, at 1280px and 375px
  - Commit: `test: month close browser coverage`

## M5 — Forecast, goals, emergency fund, net worth

- [ ] **m5-forecast-screen** — The forecast
  - Refs: FC-01, FC-02, FC-03, FC-04, FC-05, FC-06 (consumption)
  - Build: `app/Http/Controllers/ForecastController.php@index` · route `forecast.index` ·
    `resources/js/pages/forecast/index.tsx` — annual income, expenses, savings and savings
    rate; forecast year-end balance; emergency fund status; deviation from the original plan
    with its capture date; per-month source badge "Actual" / "Plan + actual" / "Plan"; the
    FC-04 highlight for past months that are not Complete; per-category annual Plan vs
    Forecast table · sidebar entry
  - Tests: `tests/Feature/Reports/ForecastTest.php` · browser smoke in
    `tests/Browser/ForecastTest.php` at both widths
  - Commit: `feat: forecast screen`

- [ ] **m5-emergency-fund-calculation** — The emergency fund formulas
  - Refs: §7.6
  - Build: `app/Data/EmergencyFundStatus.php` · `app/Actions/CalculateEmergencyFund.php` —
    essential monthly expenses by integer division, suggested target or custom override,
    current from the latest EmergencyFund snapshot at or before today (month 0 counts),
    remaining, progress %, estimated achievement month, projected at year end
  - Notes: cap the achievement search at 600 months, then "Not reachable with the current plan".
  - Tests: `tests/Unit/Actions/CalculateEmergencyFundTest.php`
  - Commit: `feat: emergency fund calculation`

- [ ] **m5-emergency-fund-screen** — The emergency fund screen
  - Refs: EF-01, EF-02, EF-03
  - Build: `app/Actions/UpdateEmergencyFundSettings.php` — writes `emergency_fund_months`,
    `categories.is_essential`, the goal's contribution and custom target in one
    `DB::transaction()` · `app/Http/Requests/UpdateEmergencyFundRequest.php` ·
    `app/Http/Controllers/EmergencyFundController.php` (show, update) · routes
    `emergency-fund.show/update` registered **before** any `/goals/{goal}` pattern ·
    `resources/js/pages/goals/emergency-fund.tsx` ·
    `resources/js/components/finance/progress-bar.tsx` — a plain div bar, **not** shadcn
    `progress`, which pulls an unapproved dependency
  - Tests: `tests/Feature/Goals/EmergencyFundTest.php`
  - Commit: `feat: emergency fund screen`

- [ ] **m5-goal-progress-calculation** — Goal progress and the off-track rule
  - Refs: §7.7
  - Build: `app/Data/GoalProgress.php` · `app/Actions/CalculateGoalProgress.php` — current by
    type (EmergencyFund from §7.6, YearEndBalance from C_F(12), others from
    `current_amount_cents`), remaining, progress %, estimated completion date, off-track rule
  - Notes: same 600-month cap on the estimate.
  - Tests: `tests/Unit/Actions/CalculateGoalProgressTest.php`
  - Commit: `feat: goal progress calculation`

- [ ] **m5-goals-screen** — Goals CRUD
  - Refs: GOAL-01, GOAL-02, GOAL-03, GOAL-04
  - Build: `app/Actions/{CreateGoal,UpdateGoal,ArchiveGoal}.php` ·
    `app/Http/Requests/{Store,Update}GoalRequest.php` ·
    `app/Http/Controllers/GoalController.php` (index, store, update) ·
    `app/Http/Controllers/GoalArchiveController.php@store` · routes
    `goals.index/store/update`, `goal-archive.store` · `resources/js/pages/goals/index.tsx` ·
    `resources/js/components/goals/goal-card.tsx` — EmergencyFund and YearEndBalance current
    amounts read-only with an explanation, others edited inline · sidebar entry
  - Tests: `tests/Feature/Goals/GoalControllerTest.php` ·
    `tests/Feature/Isolation/GoalIsolationTest.php` · browser smoke at both widths
  - Commit: `feat: financial goals`

- [ ] **m5-net-worth-calculation** — Net worth and carried-forward values
  - Refs: §7.8
  - Build: `app/Data/{NetWorthPosition,NetWorthMonth}.php` ·
    `app/Actions/CalculateNetWorth.php` — NW(0..12) by kind, missing snapshots carrying the
    most recent earlier value forward and marked as such, change vs previous month and vs
    month 0
  - Tests: `tests/Unit/Actions/CalculateNetWorthTest.php`
  - Commit: `feat: net worth calculation`

- [ ] **m5-net-worth-screen** — Net worth and its holdings
  - Refs: NW-01, NW-02, NW-03, NW-04
  - Build: `app/Actions/{CreateNetWorthItem,UpdateNetWorthItem,DeactivateNetWorthItem}.php` ·
    `app/Http/Requests/{Store,Update}NetWorthItemRequest.php` ·
    `app/Http/Controllers/NetWorthController.php@index` ·
    `app/Http/Controllers/NetWorthItemController.php` (store, update, destroy = deactivate) ·
    routes `net-worth.index`, `net-worth-items.store/update/destroy` ·
    `resources/js/pages/net-worth/index.tsx` ·
    `resources/js/components/net-worth/net-worth-chart.tsx` reusing `chart-data-table.tsx` ·
    carried-forward values muted with a tooltip · sidebar entry
  - Tests: `tests/Feature/NetWorth/NetWorthControllerTest.php` ·
    `tests/Feature/Isolation/NetWorthItemIsolationTest.php` · browser smoke at both widths
  - Commit: `feat: net worth screen`

- [ ] **m5-golden-dataset-part-2** — The fixture year, forecast half <!-- gate -->
  - Refs: TST-02 (part)
  - Build: extend `tests/Fixtures/GoldenYear.php`
  - Tests: `tests/Feature/GoldenDataset/ForecastGoalsAndNetWorthTest.php`
  - Commit: `test: golden dataset for forecast and goals`

## M6 — Subscriptions

- [ ] **m6-subscription-schema-and-billing** — The subscription record and its billing sequence
  - Refs: SUB-03, EDGE-05, §6.3
  - Build: migrations `create_subscriptions_table`,
    `add_subscription_id_to_plan_items_table`, `add_subscription_id_to_transactions_table`
    (both nullable, `nullOnDelete`, indexed — they were never created and merged migrations are
    never edited) · `app/Models/Subscription.php` with `nextBillingDateFrom(CarbonInterface)`,
    `billingMonthsIn(int $year)` (calendar-month stepping from `billing_anchor_date`, clamped to
    the month's last day per EDGE-05, zeroed after `deactivated_on`) and
    `monthlyEquivalentCents()` · relation and cast on `app/Models/{PlanItem,Transaction}.php` ·
    `database/factories/SubscriptionFactory.php` with `monthly`, `annual` and `inactive` states ·
    `app/Policies/SubscriptionPolicy.php`
  - Notes: the model and its methods must land **with** their tests or the coverage gate fails.
  - Tests: `tests/Unit/Models/SubscriptionTest.php` · extend
    `tests/Feature/Isolation/PolicyIsolationTest.php`
  - Commit: `feat: subscription schema and billing dates`

- [ ] **m6-subscription-crud-screen** — Managing subscriptions
  - Refs: SUB-01, SUB-02, CAT-07
  - Build: `app/Actions/{CreateSubscription,UpdateSubscription,DeactivateSubscription,ActivateSubscription}.php`
    — each creates or reuses by name a subcategory under the chosen category (SUB-02) ·
    `app/Http/Requests/{Store,Update}SubscriptionRequest.php` ·
    `app/Http/Controllers/SubscriptionController.php` (index, store, update) ·
    `app/Http/Controllers/SubscriptionActivationController.php` (store, destroy) · routes
    `subscriptions.index/store/update`, `subscription-activation.store/destroy` ·
    `resources/js/pages/subscriptions/index.tsx` with monthly-equivalent per row and the total
    monthly and annual cost of active subscriptions · extend `Category::isInUse()` and
    `CategoryPolicy` so the `subscriptions` system category cannot be deactivated while active
    subscriptions exist · sidebar entry
  - Tests: `tests/Feature/Subscriptions/SubscriptionControllerTest.php` ·
    `tests/Feature/Isolation/SubscriptionIsolationTest.php`
  - Commit: `feat: subscriptions crud`

- [ ] **m6-subscription-plan-sync** — Generated plan items that stay in step
  - Refs: SUB-04, SUB-05, YEAR-03
  - Build: `app/Actions/SyncSubscriptionPlanItems.php` — exactly one `source = Subscription`
    plan item per active subscription per financial year, months following its billing dates;
    re-syncs the current and future years only, past years untouched; deactivation zeroes months
    after `deactivated_on` · call it from all four subscription Actions and from
    `app/Actions/{CreateFinancialYear,CopyFinancialYear}.php` · SUB-05 double-count warning from
    `PlanController@expensesProps`, rendered in `resources/js/pages/plan/expenses.tsx`
    (case-insensitive exact name match)
  - Notes: this changes `CopyFinancialYear`, an M2 Action — re-run `tests/Feature/Planning/`
    inside this item.
  - Tests: `tests/Feature/Subscriptions/SyncSubscriptionPlanItemsTest.php`
  - Commit: `feat: sync subscription plan items`

- [ ] **m6-subscription-transaction-link** — Linking a transaction to its subscription <!-- gate -->
  - Refs: SUB-06, TST-04 (subscription slice)
  - Build: `CreateTransaction` / `UpdateTransaction` set `subscription_id` when the chosen
    subcategory belongs to an active subscription · surface it in the transaction row
  - Tests: `tests/Feature/Subscriptions/SubscriptionTransactionLinkTest.php` ·
    `tests/Browser/SubscriptionsTest.php` — create a subscription, see its generated plan item,
    record a transaction against its subcategory, at both widths
  - Commit: `feat: link transactions to subscriptions`

## M7 — Dashboard, alerts, polish

- [ ] **m7-alerts** — Derived in-app alerts
  - Refs: ALRT-01, ALRT-02, ALRT-03, ALRT-04, ALRT-05, ALRT-06, ALRT-07
  - Build: `app/Data/Alert.php` · `app/Actions/BuildAlerts.php` composing
    `CalculateMonthlyFigures`, `CalculateVariances`, `CalculateCashFlow`,
    `CalculateEmergencyFund` and `CalculateGoalProgress`, sorted 07, 06, 01/02, 03, 04, 05,
    each with a plain-language title, a one-sentence explanation and one action link ·
    `severity()`, `title()` and `explanation()` on `app/Enums/AlertType.php` ·
    `resources/js/components/finance/alerts-panel.tsx`
  - Notes: ALRT-06 for a non-current year — future year: all 12 months; past year: no alert.
    Every enum arm needs a test for the coverage gate.
  - Tests: `tests/Unit/Actions/BuildAlertsTest.php` — one case per alert type plus the empty case
  - Commit: `feat: in-app alerts`

- [ ] **m7-dashboard-cards** — The dashboard's primary and secondary blocks
  - Refs: DASH-01, DASH-02, DASH-03, DASH-05, DASH-06, YEAR-08, UX-09
  - Build: `app/Data/DashboardData.php` · `app/Actions/BuildDashboard.php` ·
    `app/Http/Controllers/DashboardController.php@index` replacing the closure in
    `routes/web.php` · rewrite `resources/js/pages/dashboard.tsx` — exactly six primary cards in
    PRD order, the compact secondary row (net worth, completed months, issue count only when
    above zero), every card linking to its detail screen, redirect to `/years/create` when the
    user has no years · `resources/js/components/finance/metric-card.tsx`
  - Tests: `tests/Feature/Dashboard/DashboardControllerTest.php`
  - Commit: `feat: dashboard cards`

- [ ] **m7-dashboard-charts** — Five lazily loaded charts
  - Refs: DASH-04, DASH-05, FE-08, NFR-04
  - Build: `Inertia::defer()` prop per chart from `DashboardController` ·
    `resources/js/components/dashboard/{closing-balance-chart,expenses-by-category-chart,income-expense-chart,net-worth-trend-chart,goals-progress-chart}.tsx`,
    each with a pulsing skeleton and the shared "View as table" fallback · alerts panel above
    the charts
  - Tests: `tests/Feature/Dashboard/DashboardChartsTest.php` — each deferred prop resolves
  - Commit: `feat: dashboard charts`

- [ ] **m7-demo-seeder** — The local demo year
  - Refs: §12.2
  - Build: `database/seeders/DemoSeeder.php` — a 2027 with a salary model at 1.800,00 € on 14
    payments, a budget across the essential categories, 3 irregular items one of them Spread,
    3 subscriptions, 2 goals, an opening position, about 60 transactions a month for
    January–June with January–April Complete, May and June InProgress and one flagged
    transaction in June, and net worth snapshots for months 0–4 · wired into `DatabaseSeeder`
    behind the existing non-production guard
  - Tests: `tests/Feature/DemoSeederTest.php` smoke test
  - Commit: `feat: local demo dataset`

- [ ] **m7-golden-dataset-complete** — Dashboard figures and alerts in the fixture
  - Refs: TST-02
  - Build: extend `tests/Fixtures/GoldenYear.php`
  - Tests: `tests/Feature/GoldenDataset/DashboardAndAlertsTest.php` — every dashboard figure and
    the full alert list
  - Commit: `test: golden dataset for dashboard and alerts`

- [ ] **m7-empty-states-and-edges** — Empty, zero and edge states across every screen
  - Refs: EDGE-01, EDGE-02, EDGE-03, EDGE-04, EDGE-05, UX-06, UX-07
  - Build: `resources/js/components/finance/empty-state.tsx` applied to every list and chart
    with one next action · the setup banner used on months, comparison, cash flow, forecast and
    dashboard · "—" for every null percentage · "Future date" badge · `payment_day` clamped to
    the month's last day
  - Tests: `tests/Feature/EdgeStatesTest.php` including February in a leap and a non-leap year
  - Commit: `feat: empty and edge states`

- [ ] **m7-glossary-and-accessibility** — Plain language and keyboard reach
  - Refs: UX-04, UX-08, UX-13, NFR-04, NFR-06
  - Build: `resources/js/components/finance/glossary-term.tsx` carrying the §5.3 copy
    **verbatim**, applied on first appearance per screen · every table over four columns audited
    for the mobile card list (the budget grid excepted, it switches to one month at a time) ·
    `aria-label` on every chart · labelled inputs, visible focus and keyboard-operable overlays
  - Tests: extend the browser suite with keyboard operation of quick add
  - Commit: `feat: glossary tooltips and accessibility pass`

- [ ] **m7-privacy** — Financial data stays out of the logs
  - Refs: NFR-05
  - Build: audit Action logging so only IDs are written; confirm `/up` and exception context
    carry no amounts or descriptions
  - Tests: `tests/Feature/PrivacyTest.php`
  - Commit: `test: keep financial data out of logs`

- [ ] **m7-browser-core-flows** — The remaining browser coverage <!-- gate -->
  - Refs: TST-04, TST-05, UX-10, §2.2
  - Build: `tests/Browser/DashboardTest.php` · extend `tests/Browser/PlanningTest.php` with the
    wizard end to end at 375px · a §2.2 acceptance walk over the demo dataset
  - Tests: every authenticated page visited with no JavaScript and no console errors, at 1280px
    and 375px
  - Commit: `test: core flow browser coverage`

---

## Deferred — SHOULD items

Not scheduled. The loop stops before these; the user decides whether to pick them up.

- TXQ-05 — description autocomplete filling category, subcategory and account
- TXQ-09 — non-blocking "Possible duplicate" hint
- MON-07 — reconciliation hint when recorded cash differs from C_A(m)
- SUB-07 — widget listing subscriptions charging in the next 30 days
- MET-02 — `docs/metrics.md` with read-only SQL for each brief metric
- TST-06 — the NFR-01 performance benchmark in its own Pest group, excluded from the default run
