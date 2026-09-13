# PRD — Personal Finance Planner & Tracker (v1)

**Status:** Draft v1.0 · **Date:** 2026-09-11
**Depends on:** `docs/laravel-foundation-requirements.md` (v0.3) — Milestone 0 must be complete before any milestone in this document starts.
**Source:** Greek product brief "PRD — Personal Finance Planner & Tracker", adopted in full and made implementation-ready. Section 19 maps every item of the original brief to requirement IDs.

---

## 0. How Claude Code must use this document

1. Read `docs/laravel-foundation-requirements.md` first. Every rule there (Sail, Actions, Cruddy controllers, strict typing, 100% coverage, browser tests, no AI attribution) applies to all work here and is not repeated.
2. Work **one milestone at a time** (section 16), in order. Start each milestone in plan mode: list the requirement IDs in scope, the migrations, Actions, controllers, pages, and tests you will create, then wait for approval.
3. **Section 7 (calculation rules) is normative.** Implement formulas exactly as written. If a formula seems wrong or ambiguous, stop and ask; do not improvise financial logic.
4. Anything not described here is out of scope (section 17). Do not add features, settings, or dependencies beyond this document and the foundation.
5. Reference requirement IDs in PR titles and descriptions (e.g. `feat(transactions): quick add [TXQ-01..TXQ-09]`).
6. When a decision is made during implementation that future sessions must respect, record it with Boost's `record-rule` tool.
7. The open questions in section 18 have defaults. Implement the default unless the question has been answered in this file.

Requirement levels: **MUST** is required for v1; **SHOULD** may be deferred with a note in the PR.

---

## 1. Product summary

A private web application where each user organises their personal finances in one place. It is simultaneously:

- a **planner** for the year's income, expenses, savings, and goals;
- a **tracker** for real income and expense transactions;
- a **forecasting tool** that projects the financial position to the end of the year.

v1 replaces the user's existing Excel workbook and covers all of its functionality. It must stay simple: no accounting or finance knowledge is required to use it.

---

## 2. Goals and success criteria

### 2.1 User goals

The user can easily:

1. Create an annual financial plan.
2. Record every real income and expense.
3. Categorise transactions.
4. Compare the plan with actual results.
5. See the forecast for the remaining months.
6. Track available money, savings, and net worth.
7. Spot deviations and categories with increased spending.

### 2.2 v1 is functionally complete when the user can

- Create a financial plan for 2027 and enter the opening balance.
- Plan monthly and one-off income, and monthly and annual expenses.
- Manually record every real transaction, with category and subcategory.
- See Actuals per month and per category automatically.
- Compare Plan and Actual, and see an updated Forecast.
- Complete and reopen a month.
- Track cash flow and balance, emergency fund, and net worth.
- Find incorrect or incomplete transactions.
- Use the core features without instructions or financial knowledge.

### 2.3 Primary usability target

**A simple transaction can be recorded in under 15 seconds** from pressing "+ New transaction" to saved (verified by TXQ-10 and MET-01).

---

## 3. Relationship to the foundation

### 3.1 Inherited decisions (not repeated here)

Laravel 13, Inertia v3, React 19, shadcn/ui, MySQL, Redis, Sail, Forge, Fortify with invitation-only onboarding, mandatory email verification, English UI, Nuno Maduro conventions, 100% coverage, browser tests from day one, Claude Code with Boost, no AI attribution.

### 3.2 Resolved conflicts between the brief and the foundation

| Brief says | Foundation says | Resolution for v1 |
|---|---|---|
| UI labels in Greek (e.g. "+ Νέα συναλλαγή"); "Language" setting | Single locale: English (OD-04) | **English UI**; the button reads "+ New transaction". Language setting not exposed in v1. See Q-01. |
| Amounts shown as `€83,33` | Formatting was `Intl('en')` | Foundation FE-18 amended: money and dates use the user's **format locale**, default `el-GR` (`83,33 €`, `31/12/2027`). |
| "Currency" setting | — | Stored per user, **fixed to EUR** in v1, not editable. |
| "Financial year start" setting | — | Stored per user, **fixed to calendar year** in v1, not editable. |
| Brief lists roles implicitly as "User" | Invitation-only, admins invite | Every user owns only their own data. **Admins cannot see other users' finances.** |

### 3.3 Approvals granted by this PRD (foundation STACK-03 and ARCH-12)

| Type | Item | Reason |
|---|---|---|
| Dependency | `recharts`, via `sail bunx shadcn@latest add chart` | Dashboard and report charts. |
| Dependency | `cmdk`, via `sail bunx shadcn@latest add command` | Searchable category combobox in quick add. |
| Folder | `app/Data` | `final readonly` DTOs returned by calculation Actions. |
| Folder | `app/ValueObjects` | The `Money` value object. |

No date library is added: dates use native `<input type="date">` and `Intl`. No money library is added: money is an in-house value object over integer cents.

---

## 4. Users, access, and data isolation

| ID | Requirement | Level |
|---|---|---|
| USR-01 | Every finance record belongs to exactly one user, directly (`user_id`) or through a parent that does (e.g. `plan_item_amounts` → `plan_items` → `financial_years` → `users`). | MUST |
| USR-02 | Every query is scoped to the authenticated user: route model binding is scoped, lookups go through the user's relationships, and every model has a Policy. A resource belonging to another user returns **404** (not 403), so its existence is not revealed. | MUST |
| USR-03 | A feature test per resource type asserts that user B cannot view, update, delete, or reference (e.g. use as `category_id`) user A's records. | MUST |
| USR-04 | When an invited user accepts their invitation, `ProvisionUserDefaults` creates their preferences, default categories and subcategories (section 12), a default "Cash" account, and an Emergency Fund goal, in the same transaction as account creation. | MUST |
| USR-05 | Admins have no access to other users' financial data. The only admin capability is inviting users (foundation INV-01). | MUST |

---

## 5. UX principles and design language

### 5.1 Principles (each is testable)

| ID | Principle | How it is met |
|---|---|---|
| UX-01 | Recording a transaction takes as few steps as possible. | Quick add (8.9.1): 3 required inputs in the common case (amount, category, description); date and type have defaults. |
| UX-02 | The user enters real amounts only once. | Actuals, variances, balances, and forecasts are computed from transactions and never entered or stored (7.1). |
| UX-03 | All Actuals and metrics are calculated automatically. | Section 7. |
| UX-04 | Financial terms are explained in plain language. | Glossary tooltips (5.3) next to every term on first appearance per screen. |
| UX-05 | The most important information appears first. | Each screen defines a primary block (section 10). |
| UX-06 | Forms have safe defaults. | Defaults listed per form; destructive actions require confirmation. |
| UX-07 | Few mandatory fields. | Each form marks its required fields; everything else is under "More details". |
| UX-08 | Plan, Actual, and Forecast are always clearly distinguished. | Fixed visual language (5.2). |
| UX-09 | No overloaded screens. | Max 6 metric cards above the fold on the dashboard; details behind drill-downs. |
| UX-10 | Core use works equally well on desktop and mobile. | Every page is designed at 375 px and 1280 px widths; browser tests run at both sizes for the core flows. |

### 5.2 Plan / Actual / Forecast visual language

Used consistently in charts, tables, legends, and badges. Colour is never the only signal (foundation FE-14): each series also has a label and a distinct line style.

| Series | Label | Line style | Colour token |
|---|---|---|---|
| Plan | "Plan" | dashed | `--series-plan` (neutral grey) |
| Actual | "Actual" | solid, thicker | `--series-actual` (primary) |
| Forecast | "Forecast" | dotted | `--series-forecast` (accent) |

Variance status colours use tokens `--status-ok` (green), `--status-warning` (orange), `--status-over` (red), each paired with an icon (check, alert-triangle, alert-octagon) and a text label ("Within budget", "Slightly over", "Over budget"; income: "On target", "Slightly under", "Under target").

### 5.3 Glossary (tooltip copy — use verbatim)

| Term | Plain-language explanation |
|---|---|
| Plan | What you expect to earn or spend. You set it; you can change it any time. |
| Actual | What really happened, calculated from the transactions you recorded. |
| Forecast | Our best guess for the whole year: real numbers for finished months, your plan for the rest. |
| Variance | The difference between Actual and Plan. |
| Cash flow | Money in minus money out for a period. |
| Closing balance | How much money you have at the end of a month. |
| Savings | What is left after expenses: income minus expenses. |
| Savings rate | The share of your income you keep. Savings divided by income. |
| Emergency fund | Money set aside to cover your essential expenses for a few months if your income stops. |
| Net worth | Everything you own minus everything you owe. |
| Irregular expense | A cost that doesn't happen every month, such as holidays or annual insurance. |
| Month status | Whether you have finished recording a month. Only finished months count as final. |

### 5.4 Mobile specifics

| ID | Requirement | Level |
|---|---|---|
| UX-11 | On screens narrower than 768 px, a floating "+" button (bottom right, 56 px, labelled "New transaction" for screen readers) opens quick add. On desktop the "+ New transaction" button sits in the top bar of every authenticated page. | MUST |
| UX-12 | On mobile, quick add opens as a bottom `Sheet`; on desktop as a centred `Dialog`. | MUST |
| UX-13 | Tables with more than 4 columns become card lists on mobile, except the budget grid (8.7), which switches to a one-month-at-a-time view. | MUST |
| UX-14 | Amount inputs use `inputmode="decimal"`. | MUST |

---

## 6. Domain model

### 6.1 Core concepts

- **Money** is stored and computed as **integer cents** (`unsignedBigInteger`). Direction comes from the transaction or plan item `type` (Income / Expense), never from the sign of the amount. Floats are forbidden anywhere in money logic (an architecture test enforces that `app/Actions` never casts money to float).
- **Transaction dates are calendar dates** (`date` column, no time). The month and year of a transaction come from its date. "Today" is computed in the user's timezone.
- A **financial year** is a calendar year for which the user has a plan. Transactions are not linked to a year by foreign key; they belong to the year that contains their date.
- **Plan data is stored**; **Actuals, Forecast, balances, variances, and alerts are always derived** and never stored. The only stored derived data is the **plan baseline** (FC-06).
- **Liquid balance** = money in cash and bank accounts, including the emergency fund. It is what transactions increase and decrease.
- The **emergency fund** is part of the liquid balance, tracked as a separate holding so the app can tell whether the user is dipping into it (Q-03).

### 6.2 Entities (brief section 6 → implementation)

| Brief entity | Table(s) | Notes |
|---|---|---|
| User | `users` + `user_preferences` | One preferences row per user. |
| Financial Year | `financial_years` | Unique per `(user_id, year)`. |
| Transaction | `transactions` | |
| Category, Subcategory | `categories` | One table; a subcategory has `parent_id`. One level of nesting only. |
| Budget Item, Income Plan, Irregular Expense | `plan_items` + `plan_item_amounts` | One model with `type` and `kind`; 12 stored monthly amounts per item. |
| (14-payment salary model) | `salary_models` | Generates `plan_items` with `source = SalaryModel`. |
| Account | `accounts` | Payment method / where money came from or went to. A label on transactions; not a balance holder (Q-08). |
| Subscription | `subscriptions` | Generates `plan_items` with `source = Subscription`. |
| Financial Goal | `goals` | Includes exactly one Emergency Fund goal per user. |
| Asset Snapshot, Debt Snapshot | `net_worth_items` + `net_worth_snapshots` | Items are holdings (asset or debt); snapshots are monthly values. Month `0` = opening position. |
| Monthly Status | `month_closures` | Stores only completion; status is derived (MON-01). |

### 6.3 Schema

All tables have `id` and timestamps. All FKs are indexed. Money columns end in `_cents` and are `unsignedBigInteger` unless noted.

**`user_preferences`**: `user_id` (unique FK, cascade), `currency` (char 3, default `EUR`), `format_locale` (default `el-GR`), `timezone` (default `Europe/Athens`), `salary_payments` (tinyint: 12 or 14, default 14), `default_account_id` (nullable FK → accounts, nullOnDelete), `emergency_fund_months` (tinyint 1–24, default 6), `budget_warning_threshold_percent` (tinyint 1–100, default 10).

**`accounts`**: `user_id`, `name` (unique per user), `type` enum `AccountType` (Cash, Bank, Card, Other), `is_active` (default true), `sort_order`.

**`categories`**: `user_id`, `parent_id` (nullable FK → categories, restrictOnDelete), `type` enum `TransactionType` (Income, Expense), `name`, `system_key` (nullable; unique per user; values in section 12), `is_active`, `is_essential` (expense only; used by the emergency fund), `is_irregular` (default for new plan items), `sort_order`. Unique `(user_id, parent_id, name)`.

**`financial_years`**: `user_id`, `year` (smallint), `setup_completed_at` (nullable), `baseline` (nullable JSON, FC-06), `baseline_captured_at` (nullable), `copied_from_id` (nullable FK → financial_years, nullOnDelete). Unique `(user_id, year)`.

**`plan_items`**: `financial_year_id` (cascade), `type` (`TransactionType`), `kind` enum `PlanItemKind` (Recurring, Irregular), `category_id`, `subcategory_id` (nullable), `name`, `frequency` enum `Frequency` (Once, Monthly, Quarterly, SemiAnnual, Annual, Custom), `start_month` (tinyint 1–12), `payment_day` (nullable tinyint 1–31), `is_fixed` (bool: fixed vs variable), `allocation` enum `Allocation` (LumpSum, Spread; irregular only, else LumpSum), `source` enum `PlanItemSource` (Manual, SalaryModel, Subscription), `salary_model_id` (nullable FK, cascade), `subscription_id` (nullable FK, cascade), `notes` (nullable text), `sort_order`.

**`plan_item_amounts`**: `plan_item_id` (cascade), `month` (tinyint 1–12), `amount_cents`. Unique `(plan_item_id, month)`. Exactly 12 rows per item, zero where nothing is planned.

**`salary_models`**: `financial_year_id` (unique, cascade), `name` (default "Salary"), `base_amount_cents` (monthly net salary), `payments` (JSON, schema in INC-05).

**`transactions`**: `user_id`, `type`, `occurred_on` (date), `amount_cents`, `category_id` (restrictOnDelete), `subcategory_id` (nullable, restrictOnDelete), `account_id` (nullable, nullOnDelete), `subscription_id` (nullable, nullOnDelete), `description` (string 255), `notes` (nullable text), `entry_source` enum (QuickAdd, Form, Duplicate), `entry_duration_ms` (nullable unsigned int). Indexes: `(user_id, occurred_on)`, `(user_id, category_id, occurred_on)`, `(user_id, type, occurred_on)`.

**`month_closures`**: `financial_year_id` (cascade), `month` (1–12), `completed_at` (nullable datetime). Unique `(financial_year_id, month)`.

**`goals`**: `user_id`, `type` enum `GoalType` (EmergencyFund, Investment, YearEndBalance, Purchase, DebtPayoff, Other), `name`, `target_amount_cents` (nullable for EmergencyFund when the target is computed), `target_is_custom` (bool, EmergencyFund only), `current_amount_cents` (manual; ignored for EmergencyFund and YearEndBalance, see GOAL-03), `monthly_contribution_cents` (nullable), `target_date` (nullable), `financial_year_id` (nullable; required for YearEndBalance), `archived_at` (nullable). At most one EmergencyFund goal per user.

**`net_worth_items`**: `user_id`, `name`, `kind` enum `NetWorthItemKind` (Cash, EmergencyFund, Investment, OtherAsset, Debt), `is_active`, `sort_order`.

**`net_worth_snapshots`**: `net_worth_item_id` (cascade), `financial_year_id` (cascade), `month` (tinyint 0–12; 0 = opening position), `value_cents` (for Debt: the outstanding balance, stored positive). Unique `(net_worth_item_id, financial_year_id, month)`.

**`subscriptions`**: `user_id`, `name`, `amount_cents`, `frequency` (Monthly, Quarterly, SemiAnnual, Annual only), `billing_anchor_date` (date of any past or future charge), `category_id` (default: the `subscriptions` system category), `subcategory_id` (auto-created, SUB-02), `account_id` (nullable), `is_active`, `deactivated_on` (nullable date), `notes`.

### 6.4 Enums (`app/Enums`, backed, TitleCase cases)

`TransactionType`, `AccountType`, `PlanItemKind`, `Frequency`, `Allocation`, `PlanItemSource`, `GoalType`, `NetWorthItemKind`, `MonthStatus` (NotStarted, InProgress, Complete), `VarianceStatus` (Ok, Warning, Over, NoPlan), `TransactionIssue` (NoFinancialYear, CategoryTypeMismatch, SubcategoryParentMismatch), `EntrySource`, `AlertType` (section 8.19).

---

## 7. Calculation rules (normative)

Notation for financial year *Y*, month *m* ∈ 1..12, top-level category *c*. Subcategory amounts always roll up into their parent category. All arithmetic is in integer cents. Percentages are computed at full precision and rounded half-up to one decimal only for display.

### 7.1 Base figures

- **Valid transaction:** a transaction with no `TransactionIssue` (TXV-05). Invalid transactions are listed and flagged but excluded from every figure below.
- **P(c, m)** — Plan: sum of `plan_item_amounts.amount_cents` for month *m* of items in category *c*.
- **A(c, m)** — Actual: sum of `amount_cents` of valid transactions in category *c* dated within month *m* of year *Y*.
- **Status(m)** (MON-01):
  - Complete if `month_closures.completed_at` is set;
  - otherwise InProgress if any transaction (valid or not) is dated in *m*;
  - otherwise NotStarted.
- **F(c, m)** — Forecast:
  - if Status(m) = Complete: **A(c, m)**;
  - otherwise: **max(P(c, m), A(c, m))**, for both income and expense categories.

  Rationale: an unfinished month keeps its plan, but real numbers that already exceed the plan are not ignored (Q-04).
- **Monthly totals** for series *x* ∈ {P, A, F}:
  - Iₓ(m) = Σ over income categories;
  - Eₓ(m) = Σ over expense categories;
  - Nₓ(m) = Iₓ(m) − Eₓ(m) (net cash flow).

### 7.2 Balances (brief 4.14)

- **Opening liquid balance B₀** = Σ `net_worth_snapshots.value_cents` for month 0 of *Y*, over items of kind Cash and EmergencyFund.
- **Closing balance** Cₓ(m) = B₀ + Σₖ₌₁..ₘ Nₓ(k). The closing balance of month *m* is the opening balance of month *m* + 1.
- **Planned closing balance series:** C_P(1..12).
- **Forecast closing balance series:** C_F(1..12).
- **Actual closing balance series:** C_A(m) for months 1..*m*ₗₐₛₜ, where *m*ₗₐₛₜ is the latest month whose status is not NotStarted, or the current month if *Y* is the current year, whichever is later. Later months are not shown.
- **Current available balance** = B₀ + Σ (valid income − valid expense) transactions dated in *Y* on or before today.

### 7.3 Annual figures

| Figure | Formula |
|---|---|
| Planned annual income / expenses | Σₘ I_P(m) / Σₘ E_P(m) |
| Actual income / expenses (dashboard, live) | Σ valid transactions in *Y* up to today, all month statuses. Labelled "Actual so far". |
| Actual income / expenses (Plan vs Actual annual view) | Σ over **Complete** months only, compared with the plan for those same months (CMP-04). |
| Forecast annual income / expenses | Σₘ I_F(m) / Σₘ E_F(m) |
| Planned savings | Σ I_P − Σ E_P |
| Actual savings (so far) | actual income so far − actual expenses so far |
| Forecast savings | Σ I_F − Σ E_F |
| Planned / forecast year-end balance | C_P(12) / C_F(12) |
| Savings rate (for series *x*) | savingsₓ ÷ incomeₓ × 100; `null` ("—") when incomeₓ = 0 |
| Deviation from original plan | C_F(12) − baseline planned year-end balance (FC-06); if no baseline, compared with C_P(12) and labelled "vs current plan" |

### 7.4 Variance (brief 4.12)

For any category/period with plan *P* and actual *A*:

- Variance V = A − P.
- Variance % = V ÷ P × 100; `null` when P = 0.
- With *t* = `budget_warning_threshold_percent` ÷ 100:

| | Ok (green) | Warning (orange) | Over (red) |
|---|---|---|---|
| **Expense** | A ≤ P | P < A ≤ P × (1 + t) | A > P × (1 + t), or P = 0 and A > 0 |
| **Income** | A ≥ P | P × (1 − t) ≤ A < P | A < P × (1 − t) |

- P = 0 and A = 0 → `NoPlan` (neutral, no colour).
- Income shortfalls are labelled "Under target", not "Over budget".
- **Spread items:** categories whose plan comes only from Spread irregular items are evaluated **year-to-date cumulative**, not per month (CMP-05), because the real payment happens in one month.

### 7.5 Plan schedules

- **Frequency prefill** from `start_month` *s* and amount *a* (the user can edit every month afterwards):

| Frequency | Months that receive the amount |
|---|---|
| Once | *s* |
| Monthly | *s*..12 |
| Quarterly | *s*, *s*+3, … ≤ 12 |
| SemiAnnual | *s*, *s*+6 ≤ 12 |
| Annual | *s* |
| Custom | the months the user ticks |

- **Spread allocation** (irregular items): the annual total *T* is split across all 12 months:
  - base = ⌊T / 12⌋ and remainder r = T − 12 × base;
  - the **last r months** get base + 1 cent, so the 12 months always sum exactly to *T*.
  - Example: €1,000.00 → months 1–8 get 8,333 cents and months 9–12 get 8,334 cents (displayed as 83,33 € / 83,34 €).
- **Lump sum:** the full amount goes to the payment month.

### 7.6 Emergency fund (brief 4.16)

- **Essential monthly expenses** E_ess = (Σₘ Σ_{c essential} P(c, m)) ÷ 12, using integer division (floor).
- **Suggested target** T_EF = `emergency_fund_months` × E_ess, unless `target_is_custom`, in which case the goal's `target_amount_cents` is used.
- **Current amount** = Σ of the latest recorded snapshot value of each active EmergencyFund item. "Latest" is the highest (year, month) at or before today, and month 0 counts.
- **Remaining** = max(0, T_EF − current). **Progress %** = min(100, current ÷ T_EF × 100).
- **Estimated achievement month**: the first month *k* ≥ 1 after the current month for which current + k × `monthly_contribution_cents` ≥ T_EF.
  - If current ≥ T_EF: "Reached".
  - If there is no contribution: "Not reachable with the current plan".
- **Projected at year end** = min(T_EF, current + contribution × remaining months in the year).

### 7.7 Goals (brief 4.15)

- **Current amount by type:**
  - EmergencyFund: 7.6.
  - YearEndBalance: current = C_F(12) of the goal's year (the forecast).
  - All others: `current_amount_cents`, updated manually.
- **Remaining** = max(0, target − current). **Progress %** = min(100, current ÷ target × 100).
- **Estimated completion date** = first day of (current month + ⌈remaining ÷ monthly_contribution⌉ months). It is `null` when there is no contribution. For YearEndBalance the estimate is "31 Dec" if C_F(12) ≥ target, otherwise "Not on track".
- **Off track** when the estimated date is after `target_date`, or when there is no contribution while remaining > 0 and a `target_date` is set.

### 7.8 Net worth (brief 4.17)

- NW(m) = Σ Cash + Σ EmergencyFund + Σ Investment + Σ OtherAsset − Σ Debt, using each active item's snapshot for month *m*.
- A **missing snapshot** carries forward the item's most recent earlier value (month 0 included) and is marked "carried forward" in the UI.
- **Change vs previous month** = NW(m) − NW(m − 1). **Change vs start of year** = NW(m) − NW(0).
- **Reconciliation** (SHOULD, MON-07): difference = recorded liquid snapshot (Cash + EmergencyFund) for month *m* − C_A(m).

---

## 8. Functional requirements

### 8.1 Preferences (brief section 8)

| ID | Requirement | Level |
|---|---|---|
| PREF-01 | Settings → Preferences shows: format locale (`el-GR`, `en-GB`, `en-US`), timezone (IANA list, default `Europe/Athens`), number of salary payments (12 or 14), default account, emergency fund coverage (3, 6, or custom 1–24 months), and budget warning threshold (1–100%, default 10). | MUST |
| PREF-02 | Currency (EUR) and financial year start (January) are shown read-only with the note "More options coming later". | MUST |
| PREF-03 | "Default categories" from the brief is covered by category management (8.3): order, activation, and the essential flag. | MUST |
| PREF-04 | Changing the format locale or timezone takes effect on the next page load without logging out. | MUST |

### 8.2 Accounts

| ID | Requirement | Level |
|---|---|---|
| ACC-01 | CRUD for accounts (name, type). Accounts in use are deactivated instead of deleted; inactive accounts are hidden from pickers but still shown on existing transactions. | MUST |
| ACC-02 | Quick add preselects the most recently used account; otherwise the preference default; otherwise none. | MUST |

### 8.3 Categories and subcategories (brief 4.8)

| ID | Requirement | Level |
|---|---|---|
| CAT-01 | Users can create, rename, reorder, activate, and deactivate categories, choose their type (Income or Expense) at creation, and create subcategories under a top-level category. | MUST |
| CAT-02 | A subcategory inherits and must match its parent's type. Only one level of nesting is allowed. | MUST |
| CAT-03 | A category's type cannot be changed once any transaction or plan item uses it. | MUST |
| CAT-04 | Categories and subcategories used by transactions or plan items cannot be deleted, only deactivated. Deactivated categories are hidden from pickers; historical data stays valid and visible. | MUST |
| CAT-05 | Moving a subcategory to another parent (same type) asks whether to also update existing transactions and plan items. If the user declines, the affected transactions get the `SubcategoryParentMismatch` issue. | MUST |
| CAT-06 | Expense categories have an "Essential (counts toward emergency fund)" toggle and an "Irregular by default" toggle. | MUST |
| CAT-07 | System categories (those with a `system_key`) can be renamed but not deleted. The `subscriptions` category cannot be deactivated while active subscriptions exist. | MUST |
| CAT-08 | Reports show top-level categories; each row expands to show its subcategory breakdown, plus "No subcategory" for the remainder. | MUST |

### 8.4 Financial years and setup wizard (brief 4.1, 5.1)

| ID | Requirement | Level |
|---|---|---|
| YEAR-01 | Users can create a financial year for any calendar year from 2000 to the current year + 5. One per year per user. | MUST |
| YEAR-02 | On creation, the user chooses **Start empty** or **Copy from {previous year}**, shown only if an earlier year exists. | MUST |
| YEAR-03 | Copying duplicates all Manual and SalaryModel plan items with their 12 monthly amounts and the salary model. Subscription plan items are regenerated from active subscriptions (SUB-04). Opening position items are copied with values prefilled from the source year's month-12 snapshots; if none exist, the liquid total is prefilled with C_A(12) if December is Complete, else C_F(12). Goals are not copied (they are user-level). Transactions are never copied. | MUST |
| YEAR-04 | Creating a year starts a **setup wizard** with these steps: 1 Opening position → 2 Income → 3 Monthly budget → 4 Irregular expenses → 5 Goals → 6 Review. Steps 2–5 can be skipped; progress is saved per step; leaving and returning resumes at the first incomplete step. | MUST |
| YEAR-05 | The Review step shows the initial forecast summary (planned income, expenses, savings, year-end balance, and emergency fund status). **Finish setup** sets `setup_completed_at` and captures the baseline (FC-06). | MUST |
| YEAR-06 | Plan amounts can be changed at any time after setup. Plan changes never modify transactions (UX-02). | MUST |
| YEAR-07 | A year switcher in the top bar lists the user's years and defaults to the current calendar year if it exists, otherwise the latest year. The selected year is kept in the URL (`/years/{year}/…`). | MUST |
| YEAR-08 | A user with no financial years is redirected from the dashboard to "Create your first plan". | MUST |

### 8.5 Opening position (brief 4.2)

| ID | Requirement | Level |
|---|---|---|
| OPEN-01 | Wizard step 1 (also editable later) records month-0 snapshot values for net worth items, grouped as: Cash & bank accounts, Emergency fund, Investments, Other assets, Debts. Users can add multiple items per group (e.g. two bank accounts). | MUST |
| OPEN-02 | First-time defaults: one item per group ("Cash & bank", "Emergency fund", "Investments", "Other assets", "Debts"), all 0, so the fastest path is to type 1–2 numbers. | MUST |
| OPEN-03 | The step shows the resulting opening liquid balance B₀ and opening net worth live as the user types. | MUST |

### 8.6 Planned income and the 14-payment salary model (brief 4.3)

| ID | Requirement | Level |
|---|---|---|
| INC-01 | Users can add income plan items with name, category, amount, frequency, start month or payment day, notes, and an editable 12-month schedule (7.5). | MUST |
| INC-02 | Default income categories are listed in section 12. | MUST |
| INC-03 | Wizard step 2 starts with the **salary model** card: "Monthly net salary" plus "Do you get the 14-payment model?" (preselected from the `salary_payments` preference). | MUST |
| INC-04 | The salary model generates plan items: Salary (Monthly, months 1–12), Christmas bonus, Easter bonus, and Vacation allowance, each linked to its system category (section 12). | MUST |
| INC-05 | `salary_models.payments` JSON holds, for each of `christmas_bonus`, `easter_bonus`, and `vacation_allowance`: `enabled` (bool), `month` (1–12), `mode` (`Multiplier` or `FixedAmount`), `multiplier` (decimal as string, e.g. `"0.5"`), and `amount_cents`. Defaults: Christmas 1.0 in December; Easter 0.5 in April; Vacation allowance 0.5 in June (Q-09). With 12 payments, all three start disabled. | MUST |
| INC-06 | Editing the salary model regenerates its plan items. Months the user edited by hand on those items are overwritten after a confirmation dialog that lists them. | MUST |
| INC-07 | The model is a default, not a rule: the user can disable any payment, change amounts, months, and calculation mode, or delete the model and plan salary manually. | MUST |
| INC-08 | Helper text: "Enter the amount that actually reaches your bank account (net). Bonuses may be taxed differently; adjust their amounts if needed." | MUST |

### 8.7 Planned expenses / monthly budget (brief 4.4)

| ID | Requirement | Level |
|---|---|---|
| BUD-01 | Users can add expense plan items with name, category/subcategory, planned amount, frequency, payment month/day, fixed or variable, irregular flag, and notes. | MUST |
| BUD-02 | **Budget grid** (wizard step 3 and Plan → Expenses). Desktop: rows = expense categories, columns = 12 months + annual total, cells editable inline. Mobile: pick a month, then edit per category. Each row has a "Same amount every month" action. | MUST |
| BUD-03 | Editing a grid cell for a category with exactly one Manual Recurring item edits that item's month. For a category with several items, the cell is read-only and shows their sum with a link to edit the items. A category with no items gets a Manual Recurring item created on first entry (named after the category). | MUST |
| BUD-04 | Every row shows its annual total; the footer shows monthly and annual totals of planned expenses. | MUST |

### 8.8 Annual and irregular expenses (brief 4.5)

| ID | Requirement | Level |
|---|---|---|
| IRR-01 | Wizard step 4 and Plan → Irregular list irregular expense items (default categories: Holidays, Annual Insurance, Car Expenses, AADE (Taxes), Large Purchases, Other Irregular Expenses). | MUST |
| IRR-02 | Each item has an annual amount, payment month, and **Allocation**: "Pay in {month}" (LumpSum) or "Set aside monthly" (Spread, 7.5). The form previews the resulting monthly amount (e.g. "83,33 € / month"). | MUST |
| IRR-03 | Spread items are marked in reports with a "Set aside monthly" badge, and variance is evaluated year-to-date (7.4). | MUST |

### 8.9 Transactions (brief 4.6, 4.7, 4.19, 4.20)

#### 8.9.1 Quick add

| ID | Requirement | Level |
|---|---|---|
| TXQ-01 | "+ New transaction" is visible on every authenticated page (UX-11) and opens quick add without navigating (UX-12). Keyboard shortcut `N` on desktop, ignored while typing in an input. | MUST |
| TXQ-02 | Field order: 1 Expense / Income segmented control (default **Expense**) → 2 Amount (autofocused) → 3 Category → 4 Description → 5 Date (default **today** in the user's timezone) → 6 "More details" (collapsed): subcategory, account (default per ACC-02), notes. | MUST |
| TXQ-03 | The amount field accepts `12,50`, `12.50`, `1.234,56`, and `1234.56`, is parsed server-side into cents (Form Request `prepareForValidation`), and shows the formatted value on blur. | MUST |
| TXQ-04 | The category field is a searchable combobox (`cmdk`) showing only active categories of the selected type. The user's 5 most-used categories of the last 90 days appear first, then all categories alphabetically. Typing a subcategory name (e.g. "Supermarket") matches it and fills both category and subcategory. | MUST |
| TXQ-05 | The description field autocompletes from the user's previous descriptions of the same type. Selecting a suggestion also fills category, subcategory, and account from the most recent transaction with that description (fields the user already set are not overwritten). | SHOULD |
| TXQ-06 | Buttons: **Save** (closes) and **Save & add another** (keeps type, date, and account; clears amount, category, and description; refocuses amount). `Enter` in the last field triggers Save; `Ctrl/Cmd+Enter` triggers Save & add another. | MUST |
| TXQ-07 | After saving, a toast confirms ("Expense 12,50 € · Food & Groceries saved") and the current page's data reloads via partial reload, so actuals, dashboard, and lists reflect the new transaction immediately. | MUST |
| TXQ-08 | If the date falls in a year without a financial year, an inline warning says "You don't have a {year} plan yet. This transaction won't count in reports until you create it." Saving is still allowed (issue `NoFinancialYear`). | MUST |
| TXQ-09 | A non-blocking "Possible duplicate" hint appears when a transaction with the same date, type, amount, and description already exists. | SHOULD |
| TXQ-10 | The client measures time from dialog open to successful save and sends it as `entry_duration_ms` with `entry_source = QuickAdd` (MET-01). | MUST |

#### 8.9.2 Full form, duplicate, delete

| ID | Requirement | Level |
|---|---|---|
| TXF-01 | Editing a transaction opens the same form, prefilled, in the same Dialog/Sheet. | MUST |
| TXF-02 | "Duplicate" opens the form prefilled with the original's fields and **today's date**; saving creates a new transaction with `entry_source = Duplicate`. | MUST |
| TXF-03 | Delete asks for confirmation ("Delete this 12,50 € expense from 3 March?"). Deletion is permanent. | MUST |

#### 8.9.3 Validation and issues

| ID | Requirement | Level |
|---|---|---|
| TXV-01 | Blocking validation (Form Request): type required; amount required, > 0, ≤ 10,000,000.00 €, max 2 decimals; category required, owned by the user, active, top-level, same type as the transaction; subcategory optional, owned, active, child of the chosen category; account optional, owned, active; date required and a valid date; description required, 1–255 characters; notes ≤ 2,000 characters. Errors appear inline in plain language. | MUST |
| TXV-02 | Creating, editing, deleting, or bulk-recategorising a transaction dated in a **Complete** month is rejected with a clear message and a "Reopen {month} and save" action. That action reopens the month and applies the change atomically in one Action. | MUST |
| TXV-03 | A transaction is **flagged** when it has at least one `TransactionIssue`: `NoFinancialYear` (no financial year contains its date), `CategoryTypeMismatch` (category type ≠ transaction type, a defensive check), or `SubcategoryParentMismatch` (CAT-05). | MUST |
| TXV-04 | Issues are **derived on read** by a query scope (`withIssues()` / `valid()`), never stored, so they disappear the moment the cause is fixed (e.g. the missing year is created). | MUST |
| TXV-05 | Flagged transactions are excluded from every calculation in section 7, shown with a warning badge and the reason in lists, counted on the dashboard, and listed in the month review. | MUST |

#### 8.9.4 List and search

| ID | Requirement | Level |
|---|---|---|
| TXL-01 | `/transactions` lists transactions newest first, 50 per page, with server-side filtering and pagination. Filter state lives in the URL query string. | MUST |
| TXL-02 | Filters: date range, month (within the selected year), type, category, subcategory, account, amount range, description text (contains, case-insensitive), and "Only with issues". | MUST |
| TXL-03 | The row shows date, description, category › subcategory, account, and amount (expenses and income visually distinct, with a text sign "−" / "+"). Actions: view, edit, duplicate, delete. | MUST |
| TXL-04 | Bulk selection (checkbox per row, plus "select all on this page") with **Change category** (and optional subcategory) for the selected rows. It validates type compatibility and TXV-02, and reports "Updated 12 transactions" or what was skipped and why. | MUST |
| TXL-05 | The list footer shows totals for the current filter: income, expenses, net. | MUST |

### 8.10 Month status and month close (brief 4.11, 5.3)

| ID | Requirement | Level |
|---|---|---|
| MON-01 | Status is derived (7.1). Only completion is stored. Adding the first transaction therefore moves a month to In Progress automatically; deleting all transactions of an incomplete month returns it to Not Started. | MUST |
| MON-02 | `/years/{year}/months` shows 12 month cards with status badge, income, expenses, net, and issue count. | MUST |
| MON-03 | **Month review** `/years/{year}/months/{month}` in close-flow order: 1 Totals (income, expenses, net; Plan vs Actual) → 2 Issues to fix (flagged transactions of the month, each with a fix link) → 3 Budget variances by category (7.4) → 4 Net worth update (snapshot form, MON-06) → 5 **Mark month as complete**. | MUST |
| MON-04 | Completing is blocked while the month has flagged transactions ("Fix 2 transactions first") or if the month starts after today. Completing the current month before its last day asks for confirmation. | MUST |
| MON-05 | A Complete month shows **Reopen month**, which clears `completed_at` after confirmation. Forecast and comparisons update immediately. | MUST |
| MON-06 | The net worth form lists all active items, prefilled with the previous month's value (month 0 for January). The liquid items show the transaction-derived closing balance C_A(m) as a hint. Saving is optional for completion. | MUST |
| MON-07 | Reconciliation hint: when the recorded liquid total differs from C_A(m), show "Your recorded cash is 45,00 € higher than your transactions explain. Missing income?" or the equivalent for lower. | SHOULD |

### 8.11 Monthly actuals and Plan vs Actual (brief 4.10, 4.12)

| ID | Requirement | Level |
|---|---|---|
| CMP-01 | Every month view shows total actual income, total actual expenses, actual per category (expandable to subcategories), net cash flow, and opening and closing balance (7.2). | MUST |
| CMP-02 | Actuals are read-only everywhere. Each actual amount links to the filtered transaction list that produces it ("Fix it in the transaction"). | MUST |
| CMP-03 | `/years/{year}/reports/comparison` shows, per category and for a selectable month (default: latest Complete month, else current month), Plan, Actual, Variance, Variance %, and status (7.4). Income and expense sections are separate. | MUST |
| CMP-04 | The same page has a **Year to date** mode that sums Complete months only and compares them with the plan of the same months. The header states "Based on {n} completed months". | MUST |
| CMP-05 | Spread-only categories show a YTD variance in both modes, with a tooltip explaining why. | MUST |
| CMP-06 | Categories are sorted by variance severity (Over, then Warning, then Ok, then NoPlan), then by absolute variance descending, so the problem categories come first (brief goal 7). | MUST |

### 8.12 Cash flow (brief 4.14)

| ID | Requirement | Level |
|---|---|---|
| CF-01 | A 12-month cash-flow table shows per month: opening balance, income, expenses, net cash flow, closing balance, for Plan, Actual, and Forecast (7.2). The Actual column is blank for months after *m*ₗₐₛₜ. | MUST |
| CF-02 | A line chart shows the three closing-balance series with the 5.2 visual language, and a zero line. | MUST |

### 8.13 Forecast (brief 4.13)

| ID | Requirement | Level |
|---|---|---|
| FC-01 | `/years/{year}/reports/forecast` shows forecast annual income, expenses, savings, and savings rate; forecast year-end balance; emergency fund status (7.6: projected at year end and estimated achievement month); and deviation from the original plan (7.3). | MUST |
| FC-02 | Per month, a badge shows which source feeds the forecast: "Actual" (Complete), "Plan + actual" (InProgress, 7.1 max rule), or "Plan" (NotStarted). | MUST |
| FC-03 | The forecast recalculates on every request; there is no cache invalidation to get wrong (the NFR-01 performance budget applies). | MUST |
| FC-04 | Past months that are not Complete are highlighted: "March isn't marked complete, so the forecast still uses your plan for it." | MUST |
| FC-05 | Per-category forecast table: annual Plan vs Forecast with difference. | MUST |
| FC-06 | **Baseline.** Finishing setup captures a JSON baseline of the monthly planned income, expenses, and closing balance (C_P(1..12)) plus the planned annual totals. "Deviation from original plan" uses it. Plan → "Reset baseline to current plan" re-captures it after confirmation and shows the capture date. | MUST |

### 8.14 Financial goals (brief 4.15)

| ID | Requirement | Level |
|---|---|---|
| GOAL-01 | `/goals` supports CRUD for goals of types Investment, YearEndBalance (desired balance at year end), Purchase (planned purchases), DebtPayoff, and Other. The EmergencyFund goal always exists and cannot be deleted. | MUST |
| GOAL-02 | Each goal card shows target, current, remaining, progress % (bar), target date, estimated completion, and an "Off track" badge (7.7). | MUST |
| GOAL-03 | Current amount: EmergencyFund from snapshots, YearEndBalance from the forecast (both read-only with an explanation), others edited inline ("Update amount"). | MUST |
| GOAL-04 | Goals can be archived; archived goals are hidden behind a toggle. | MUST |

### 8.15 Emergency fund (brief 4.16)

| ID | Requirement | Level |
|---|---|---|
| EF-01 | `/goals/emergency-fund` shows the target (7.6), how it was computed ("6 months × 1.450,00 € essential monthly expenses"), available, remaining, progress %, estimated achievement month, and projected amount at year end. | MUST |
| EF-02 | The user can choose 3 months, 6 months, or a custom number (writes `emergency_fund_months`), pick which expense categories are essential (writes `is_essential`), set a monthly contribution, or override with a custom target amount. | MUST |
| EF-03 | If no EmergencyFund net worth item has a value yet, show "Tell us how much you already have set aside" with a link to the opening position or the latest month review. | MUST |

### 8.16 Net worth (brief 4.17)

| ID | Requirement | Level |
|---|---|---|
| NW-01 | `/goals/net-worth` shows current net worth (latest month with any snapshot), the change vs the previous month and vs the start of the year, a breakdown by kind, and a line chart of NW(0..12) for the selected year. | MUST |
| NW-02 | Items (holdings and debts) can be managed there: add, rename, change kind, deactivate. | MUST |
| NW-03 | Snapshots are entered from the month review (MON-06) or from `/goals/net-worth` for any month. The month-0 snapshot is the opening position (OPEN-01). | MUST |
| NW-04 | Carried-forward values are shown in muted text with a "carried forward" tooltip (7.8). | MUST |

### 8.17 Subscriptions (brief 4.9)

| ID | Requirement | Level |
|---|---|---|
| SUB-01 | `/subscriptions` supports CRUD: name, amount, billing frequency, next billing date, category (default Subscriptions), account, notes, active/inactive. It shows each subscription's monthly-equivalent cost and the total monthly and annual cost of active subscriptions. | MUST |
| SUB-02 | Creating a subscription creates (or reuses by name) a subcategory with the same name under its category, so transactions can be recorded against it (e.g. Subscriptions › Netflix). | MUST |
| SUB-03 | The "next billing date" field is stored as `billing_anchor_date`. The displayed next billing date is always derived as the first date ≥ today in the anchor + *k* × frequency sequence, so it never goes stale. | MUST |
| SUB-04 | For every financial year, each active subscription has exactly one generated plan item (`source = Subscription`) whose months follow its billing dates within that year. Creating, editing, deactivating, or reactivating a subscription re-syncs its plan items for the current and future financial years; past years are untouched. Deactivation zeroes months after `deactivated_on`. | MUST |
| SUB-05 | **No double counting:** the subscription plan appears once, via its generated item in its category. If a Manual plan item in the same category has a name that matches an active subscription, the plan screen warns "This may double-count {name}". | MUST |
| SUB-06 | Transactions can optionally be linked to a subscription (`subscription_id`, set automatically when its subcategory is chosen). v1 does **not** create transactions from subscriptions automatically. | MUST |
| SUB-07 | A widget lists active subscriptions charging in the next 30 days. | SHOULD |

### 8.18 Dashboard (brief 4.18)

| ID | Requirement | Level |
|---|---|---|
| DASH-01 | The dashboard is the home screen after login (`/dashboard`, for the selected year). | MUST |
| DASH-02 | **Primary cards** (max 6, in this order): Current available balance · Forecast year-end balance (with deviation from original plan) · Income: Actual so far / Plan / Forecast · Expenses: Actual so far / Plan / Forecast · Savings: actual so far, planned, and savings rate · Emergency fund progress. | MUST |
| DASH-03 | **Secondary row** (compact): net worth, completed months "4 / 12", transactions with issues (shown only if > 0, links to the filtered list). | MUST |
| DASH-04 | **Charts**, each lazy-loaded as an Inertia deferred prop with a skeleton: closing balance Plan vs Actual vs Forecast (CF-02); expenses by top-level category for the selected month, Plan vs Actual bars; monthly income and expenses (Actual for Complete months, Forecast otherwise, visually distinguished); net worth trend (NW-01); goals progress (bars). | MUST |
| DASH-05 | Alerts panel (8.19), shown above the charts when there are alerts. | MUST |
| DASH-06 | Every card and chart links to its detail screen. | MUST |

### 8.19 In-app alerts (brief section 7)

Alerts are **derived on each request** by a `BuildAlerts` Action. They are not stored, and nothing is sent by email or push.

| ID | Alert type | Condition | Links to |
|---|---|---|---|
| ALRT-01 | TransactionsWithIssues | ≥ 1 flagged transaction in the selected year, or with `NoFinancialYear` | Filtered transaction list |
| ALRT-02 | CategoryTypeMismatch | Included in ALRT-01 with its own reason text | Filtered list |
| ALRT-03 | BudgetOverrun | An expense category has status Over in the current month or the latest Complete month | Comparison |
| ALRT-04 | IncompleteMonth | A month before the current month (in the selected year) is not Complete | Month review |
| ALRT-05 | GoalOffTrack | Any goal is off track (7.7) | Goals |
| ALRT-06 | ForecastBelowEmergencyFund | Min over months ≥ current of C_F(m) is below the current emergency fund amount (you would need to use the reserve) | Forecast |
| ALRT-07 | NegativeForecastBalance | Any C_F(m) < 0 | Forecast |

Each alert has a plain-language title, a one-sentence explanation, and one action link. Alerts are sorted by severity: ALRT-07, 06, 01/02, 03, 04, 05.

### 8.20 Product metrics (brief section 11)

| ID | Requirement | Level |
|---|---|---|
| MET-01 | Transactions store `entry_source` and `entry_duration_ms` (TXQ-10). | MUST |
| MET-02 | No analytics dashboard or third-party tracking in v1. The brief's metrics (transactions per month, error-free rate, entry time, completed-month rate, budget adoption, return for monthly close, feature usage, custom category count) must be answerable with read-only SQL over existing tables; a `docs/metrics.md` file lists the queries. | SHOULD |

---

## 9. Screens and routes

All authenticated routes use `auth` and `verified` middleware. Year-scoped routes bind `{year}` to the user's `FinancialYear` by the `year` column, scoped to the user (USR-02).

| Area | Route | Page component | Primary block (UX-05) |
|---|---|---|---|
| Dashboard | `GET /dashboard` | `dashboard` | Primary cards |
| Create year | `GET /years/create` | `years/create` | Year + empty/copy choice |
| Setup wizard | `GET /years/{year}/setup/{step}` | `years/setup/{step}` | Current step form |
| Plan | `GET /years/{year}/plan/{tab}` (income, expenses, irregular, opening) | `plan/{tab}` | Annual totals |
| Months | `GET /years/{year}/months` | `months/index` | 12 status cards |
| Month review | `GET /years/{year}/months/{month}` | `months/show` | Totals + issues |
| Plan vs Actual | `GET /years/{year}/reports/comparison` | `reports/comparison` | Categories by severity |
| Cash flow | `GET /years/{year}/reports/cash-flow` | `reports/cash-flow` | Balance chart |
| Forecast | `GET /years/{year}/reports/forecast` | `reports/forecast` | Year-end balance |
| Transactions | `GET /transactions` | `transactions/index` | List + filters |
| Goals | `GET /goals` | `goals/index` | Goal cards |
| Emergency fund | `GET /goals/emergency-fund` | `goals/emergency-fund` | Progress |
| Net worth | `GET /goals/net-worth` | `goals/net-worth` | Current NW + change |
| Subscriptions | `GET /subscriptions` | `subscriptions/index` | Monthly total |
| Categories | `GET /settings/categories` | `settings/categories` | Tree |
| Accounts | `GET /settings/accounts` | `settings/accounts` | List |
| Preferences | `GET /settings/preferences` | `settings/preferences` | Form |

**Navigation:**
- Desktop sidebar: Dashboard, Transactions, Months, Plan, Plan vs Actual, Forecast, Cash flow, Goals, Net worth, Subscriptions, then Settings (Categories, Accounts, Preferences, and the foundation's Profile, Password, 2FA, Invitations).
- Mobile: the collapsible sidebar plus the floating "+" button.

---

## 10. Architecture mapping (foundation ARCH-01…ARCH-16)

### 10.1 Write Actions (`app/Actions`)

- **Users:** `ProvisionUserDefaults`, `UpdatePreferences`
- **Accounts and categories:** `CreateAccount`, `UpdateAccount`, `DeactivateAccount`, `CreateCategory`, `UpdateCategory`, `DeactivateCategory`, `ActivateCategory`, `MoveSubcategory`
- **Financial years:** `CreateFinancialYear`, `CopyFinancialYear`, `UpdateOpeningPosition`, `CompleteYearSetup`, `CapturePlanBaseline`
- **Plan items and salary:** `CreatePlanItem`, `UpdatePlanItem`, `DeletePlanItem`, `UpdateBudgetCell`, `SaveSalaryModel`, `RemoveSalaryModel`
- **Transactions:** `CreateTransaction`, `UpdateTransaction`, `DeleteTransaction`, `RecategorizeTransactions`
- **Months:** `CompleteMonth`, `ReopenMonth`, `SaveNetWorthSnapshots`
- **Goals and net worth items:** `CreateGoal`, `UpdateGoal`, `ArchiveGoal`, `UpdateEmergencyFundSettings`, `CreateNetWorthItem`, `UpdateNetWorthItem`, `DeactivateNetWorthItem`
- **Subscriptions:** `CreateSubscription`, `UpdateSubscription`, `DeactivateSubscription`, `ActivateSubscription`, `SyncSubscriptionPlanItems`

### 10.2 Read / calculation Actions

These return `final readonly` DTOs from `app/Data`: `CalculateMonthlyFigures` (P/A/F per category × month, 7.1), `CalculateCashFlow` (7.2), `CalculateAnnualSummary` (7.3), `CalculateVariances` (7.4), `BuildPlanSchedule` (7.5), `CalculateEmergencyFund` (7.6), `CalculateGoalProgress` (7.7), `CalculateNetWorth` (7.8), `BuildAlerts` (8.19), `BuildDashboard` (composes the others).

**Rules for the calculation Actions:**
- They are **pure over their inputs**: no writes, and "today" is injected, never read from `now()` inside, so tests can pin the date.
- They use grouped SQL aggregates, one query per figure family.
- They never perform per-row queries in loops (Essentials strict mode enforces this).

### 10.3 Controllers (Cruddy)

- **Dashboard, years, and plan:** `DashboardController@index`, `FinancialYearController` (create, store), `FinancialYearCopyController@store`, `YearSetupController` (show, update), `YearSetupCompletionController@store`, `PlanController@show`, `PlanItemController` (store, update, destroy), `BudgetCellController@update`, `SalaryModelController` (update, destroy), `OpeningPositionController@update`, `PlanBaselineController@store`
- **Transactions:** `TransactionController` (index, store, update, destroy), `TransactionCategoryController@update` (bulk)
- **Months and reports:** `MonthController` (index, show), `MonthCompletionController` (store = complete, destroy = reopen), `ComparisonController@index`, `CashFlowController@index`, `ForecastController@index`
- **Goals, net worth, subscriptions:** `GoalController` (index, store, update), `GoalArchiveController@store`, `EmergencyFundController` (show, update), `NetWorthController@index`, `NetWorthItemController` (store, update, destroy = deactivate), `NetWorthSnapshotController@update`, `SubscriptionController` (index, store, update), `SubscriptionActivationController` (store, destroy)
- **Settings:** `CategoryController` (index, store, update, destroy = deactivate), `CategoryActivationController@store`, `SubcategoryParentController@update`, `AccountController` (index, store, update, destroy = deactivate), `PreferencesController` (edit, update)

### 10.4 Supporting classes

- **`Money` value object** (`app/ValueObjects/Money.php`, `final readonly`, int cents):
  - `fromInput(string)` accepts the formats in TXQ-03;
  - `plus`, `minus`, `multiplyByRatio(numerator, denominator)` rounds half-up;
  - `allocate(int parts)` implements 7.5;
  - no formatting in PHP, since the frontend formats.
- **`MoneyCast`** for `*_cents` attributes.
- **Architecture test:** `App\Data` classes are final and readonly.

### 10.5 Frontend

- `lib/money.ts`: `formatMoney(cents, locale)` via `Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' })`.
- `lib/dates.ts`: `formatDate(isoDate, locale)`.
- Shared Inertia props: `preferences.formatLocale`, `preferences.timezone`, `years` (the year switcher), `selectedYear`, `abilities`.

---

## 11. Non-functional requirements

| ID | Requirement | Level |
|---|---|---|
| NFR-01 | **Performance:** with 5 financial years and 15,000 transactions per user, server time for the dashboard, comparison, and forecast is ≤ 300 ms p95 locally under Sail (measured by a seeded benchmark test). Quick add save round-trip ≤ 500 ms. | MUST |
| NFR-02 | **Correctness:** all money math uses integer cents via `Money`; an architecture test forbids `float` / `(float)` / `round(` in `app/Actions` and `app/ValueObjects` except inside `Money::multiplyByRatio`. | MUST |
| NFR-03 | **Consistency:** every write Action affecting more than one row runs in `DB::transaction()`; plan item plus its 12 amounts are always written together. | MUST |
| NFR-04 | **Accessibility:** foundation FE-14; charts have a visible data-table alternative ("View as table") and descriptive `aria-label`s. | MUST |
| NFR-05 | **Privacy:** financial data never appears in logs, exception context, or the `/up` response. Pail/log output of Actions contains IDs only. | MUST |
| NFR-06 | **Responsiveness:** core flows are usable at 375 px width without horizontal scrolling (except the budget grid's desktop mode, which is replaced on mobile per UX-13). | MUST |

---

## 12. Seed and default data

### 12.1 Provisioned per user (USR-04)

**Income categories** (system key in brackets):
- Salary [`salary`]
- Overtime
- Christmas Bonus [`christmas_bonus`]
- Easter Bonus [`easter_bonus`]
- Vacation Allowance [`vacation_allowance`]
- Business Distributions
- Freelance & Projects
- Other Income
- Extraordinary Income

**Expense categories:**

| Category | Essential | Irregular | Default subcategories |
|---|---|---|---|
| Housing | ✓ | | |
| Food & Groceries | ✓ | | Supermarket, Farmers' market (Laiki) |
| Phone & Internet | ✓ | | |
| Utilities | ✓ | | Electricity, Water |
| Transportation | ✓ | | |
| Personal Care & Health | ✓ | | |
| Subscriptions [`subscriptions`] | | | (created by SUB-02) |
| Dining Out | | | Coffee, Restaurant |
| AADE (Taxes) | | ✓ | |
| Miscellaneous | | | |
| Holidays | | ✓ | |
| Annual Insurance | | ✓ | |
| Car Expenses | | ✓ | |
| Large Purchases | | ✓ | |
| Other Irregular Expenses | | ✓ | |

**Also provisioned:**
- account "Cash" (type Cash);
- preferences with defaults (6.3);
- the EmergencyFund goal (computed target, no contribution).

### 12.2 Local demo seeder

Seeds the known local admin (foundation DB-07) with a realistic 2027:
- a salary model at 1.800,00 € net with 14 payments;
- a budget across all essential categories;
- 3 irregular items, one of them Spread;
- 3 subscriptions and 2 goals;
- opening position;
- about 60 transactions per month for January–June, with January–April Complete, May InProgress, June InProgress with one flagged transaction;
- net worth snapshots for months 0–4.

This dataset is also the browser-test fixture.

---

## 13. Testing strategy (on top of foundation §9)

| ID | Requirement | Level |
|---|---|---|
| TST-01 | **Formula tests:** every formula in section 7 has unit or feature tests with hand-computed expectations, including edge cases (P = 0, income = 0, spread remainders, max rule in InProgress months, carried-forward snapshots, EF unreachable, year boundaries on 31 Dec / 1 Jan, and user timezone vs UTC around midnight). | MUST |
| TST-02 | **Golden dataset:** a fixture year with fully specified inputs and expected outputs for every dashboard figure, the 12-month cash-flow table, variances, and alerts. It is stored as a PHP fixture and asserted in one test. If the user supplies the Excel workbook, its 2027 figures become this fixture (Q-07). | MUST |
| TST-03 | **Isolation tests** per USR-03. | MUST |
| TST-04 | **Browser tests** at 1280 px and 375 px: setup wizard end to end; quick add (common path with 3 required inputs; Save & add another; comma decimal); Complete-month guard and reopen-and-save; month close flow; bulk recategorise; subscription creation → plan item → transaction via subcategory; dashboard renders with no JS errors. | MUST |
| TST-05 | **Quick-add efficiency test:** a browser test performs the common path with ≤ 6 user interactions (open, amount, category pick, description, save) and asserts the transaction exists. | MUST |
| TST-06 | **Performance test** for NFR-01, excluded from the default parallel run via a Pest group and run in CI on `main`. | SHOULD |

---

## 14. Error, empty, and edge states

| ID | Requirement | Level |
|---|---|---|
| EDGE-01 | Every list and chart has an empty state with one next action (e.g. "No transactions in March yet — Add one"). | MUST |
| EDGE-02 | Viewing a year without setup completed shows a banner "Finish setting up {year}" linking to the wizard. | MUST |
| EDGE-03 | A zero plan in any month or category never causes division errors (variance % and savings rate show "—"). | MUST |
| EDGE-04 | Transactions dated in the future are allowed, count as Actual for their month, and show a "Future date" badge. | MUST |
| EDGE-05 | Leap years and 31-day payment days: `payment_day` greater than the month's length resolves to the last day of the month. | MUST |

---

## 15. Rollout

v1 is a single release after milestone M7 (section 16), deployed per foundation §14. No feature flags.

---

## 16. Milestones for Claude Code

Each milestone ends with `sail composer test` green, updated browser tests, a PR referencing the IDs, and review. No milestone starts before the previous one is merged.

| # | Milestone | Scope (IDs) | Done when |
|---|---|---|---|
| M0 | Foundation | Foundation doc | Foundation §17 acceptance criteria met |
| M1 | Domain core | USR-01…05, PREF-01…04, ACC-01…02, CAT-01…08, `Money`/`MoneyCast`, enums, §12.1 provisioning | Invited user gets defaults; categories and accounts manageable; isolation tests pass |
| M2 | Planning | YEAR-01…08, OPEN-01…03, INC-01…08, BUD-01…04, IRR-01…03, FC-06 (baseline capture), 7.5 | User creates 2027 via wizard (empty and copy), salary model generates 14 payments, spread allocation exact to the cent |
| M3 | Transactions | TXQ-01…10, TXF-01…03, TXV-01…05, TXL-01…05, MET-01 | Quick add < 15 s path proven by TST-05; issues derived; Complete-month guard works (tested against a closure row) |
| M4 | Actuals, months, comparison, cash flow | MON-01…07, CMP-01…06, CF-01…02, 7.1–7.4 | Month close flow end to end; golden dataset part 1 (actuals, variances, balances) passes |
| M5 | Forecast, goals, emergency fund, net worth | FC-01…06, GOAL-01…04, EF-01…03, NW-01…04, 7.6–7.8 | Golden dataset part 2 (forecast, EF, goals, NW) passes |
| M6 | Subscriptions | SUB-01…07 | Subscription plan items sync across years; no double counting |
| M7 | Dashboard, alerts, polish | DASH-01…06, ALRT-01…07, UX-01…14, NFR-01…06, EDGE-01…05, TST-04…06, §12.2 demo seeder | §2.2 criteria demonstrably met on the demo dataset, at both viewport sizes |

---

## 17. Out of scope for v1

From the brief:
- bank connections and automatic import of bank transactions;
- receipt OCR;
- real-time portfolio management, and automatic stock or crypto prices;
- tax returns;
- family or shared accounts;
- AI financial advisor;
- native mobile app;
- automatic currency conversion;
- email or push notifications;
- accounting system integrations.

Added by this PRD:
- multiple currencies and non-calendar financial years (settings stored, not editable);
- UI languages other than English;
- transfers between accounts and per-account balances;
- automatic transactions from subscriptions;
- plan versioning beyond one baseline;
- deleting financial years;
- CSV/Excel import and export;
- an analytics dashboard.

---

## 18. Open questions (defaults are implemented unless answered here)

| ID | Question | Default |
|---|---|---|
| Q-01 | The brief is in Greek, but OD-04 chose an English UI. Keep English for v1? | English UI; `el-GR` number and date formatting |
| Q-02 | Default format locale `el-GR` (`1.234,56 €`, `31/12/2027`)? | Yes |
| Q-03 | Emergency fund model: a separate holding that is part of the liquid balance (so the app can tell when the forecast would eat into it)? | Yes |
| Q-04 | Forecast for unfinished months = max(Plan, Actual) per category? | Yes |
| Q-05 | Editing a transaction in a Complete month is blocked unless the user reopens the month (in one step)? | Yes |
| Q-06 | Capture the "original plan" baseline when setup finishes, with manual reset? | Yes |
| Q-07 | Can you share the current Excel (or its 2027 numbers) to become the golden test dataset? Importing its data into the app stays out of scope. | Synthetic fixture |
| Q-08 | Accounts (payment methods on transactions) stay separate from net-worth holdings (balances)? Unifying them requires transfers, which are out of scope. | Separate |
| Q-09 | Default months: Christmas bonus December, Easter bonus April, Vacation allowance June? | Yes |
| Q-10 | Default emergency fund coverage: 6 months? | 6 |

---

## 19. Traceability — original brief → requirements

| Brief section | Covered by |
|---|---|
| 1 Summary, 2 Goals | §1, §2 |
| 3 UX principles | UX-01…UX-14, §5.2, §5.3 |
| 4.1 Annual plan | YEAR-01…08, FC-06 |
| 4.2 Opening position | OPEN-01…03, 7.2 |
| 4.3 Planned income, 14 payments | INC-01…08, 7.5 |
| 4.4 Planned expenses | BUD-01…04, 7.5 |
| 4.5 Annual / irregular | IRR-01…03, 7.4, 7.5 |
| 4.6 Transactions | TXQ, TXF, TXV, 7.1 |
| 4.7 Quick add | TXQ-01…10 |
| 4.8 Categories | CAT-01…08, §12.1 |
| 4.9 Subscriptions | SUB-01…07 |
| 4.10 Monthly actuals | CMP-01…02, 7.1 |
| 4.11 Month status | MON-01…07 |
| 4.12 Plan vs Actual | CMP-03…06, 7.4 |
| 4.13 Forecast | FC-01…06, 7.1, 7.3 |
| 4.14 Cash flow | CF-01…02, 7.2 |
| 4.15 Goals | GOAL-01…04, 7.7 |
| 4.16 Emergency fund | EF-01…03, 7.6 |
| 4.17 Net worth | NW-01…04, 7.8 |
| 4.18 Dashboard | DASH-01…06 |
| 4.19 Validation | TXV-01…05 |
| 4.20 List & search | TXL-01…05 |
| 5 User flows | YEAR-04/05 (5.1), TXQ (5.2), MON-03…06 (5.3) |
| 6 Entities | §6.2, §6.3 |
| 7 Notifications | ALRT-01…07 |
| 8 Settings | PREF-01…04, §3.2 |
| 9 Out of scope | §17 |
| 10 MVP criteria | §2.2, M7 |
| 11 Metrics | MET-01…02, §2.3, TST-05 |

---