---
description: Implement the next unchecked item in .claude/backlog.md, test it, and commit.
---

Implement **exactly one** backlog item, then stop. Do not start a second one, and do not ask
whether to continue — the loop invokes this command again.

## 1. Preflight

```bash
git rev-parse --abbrev-ref HEAD      # must be main
git status --porcelain               # must be empty
docker compose ps --format '{{.Name}}\t{{.Status}}'
```

- Wrong branch, or a dirty tree → print `BLOCKED: <reason>` and stop. Never stash, reset or
  check out to force it clean.
- `fin-laravel.test-1` not up → `vendor/bin/sail up -d` and wait for mysql to report healthy.

## 2. Pick the item

Read `.claude/backlog.md` and take the **first `[ ]` item**.

- No `[ ]` items left → print `BACKLOG COMPLETE` and stop.
- A `[!]` item appears before it → print `BLOCKED: <id> — <the note beneath it>` and stop.

## 3. Read before writing anything

These are **UI items on a finished app**, not new domain logic. Read, in this order:

1. The item's `Refs:` in `docs/prd.md` — §5 for the UX principles and the §5.3 glossary copy
   (**verbatim**, never reworded), §9 for screens and routes, §14 for empty and edge states.
2. The sibling files named in the item's `Build:` line, so the new code matches what is there.
3. The item's `Notes:`, which settle anything the PRD leaves open.

If the item is genuinely ambiguous and its `Notes:` do not settle it, mark it `[!]` with a
one-line note, commit only that backlog edit as `chore: block <id>`, and stop. Do not improvise.

## 4. Build

In this order, following sibling files for structure:

route → controller → `vendor/bin/sail artisan wayfinder:generate --with-form` → React page and
components → `vendor/bin/sail bun run build` → tests.

Items that touch the domain (rare here) keep the v1 order: migration → model + factory → enum →
`app/Data` DTO → Action → Policy → Form Request → Controller → route.

- Generate files with `vendor/bin/sail artisan make:* --no-interaction`.
- Add shadcn primitives with `printf 'n\n' | vendor/bin/sail bunx shadcn@latest add <component> --yes`
  — it prompts before overwriting an existing file, and an unanswered prompt hangs the loop.
  Answering `n` keeps the project's own version of anything it already has.
- **Run `vendor/bin/sail bun run build` after adding, moving or renaming any page**, before
  running feature tests. Inertia resolves pages through the Vite manifest, so a page that has
  not been built makes every test touching it fail with `Unable to locate file in Vite manifest`.
- Enum *values* are lowercase (`income`, `expense`); the TitleCase names are the PHP cases. Use
  `TransactionType::Income->value` in tests and the lowercase string in TypeScript.
- Every command runs through `vendor/bin/sail`. There is no host PHP, Composer, Node or Bun.

## 5. Test

```bash
vendor/bin/sail artisan test --compact --filter=<the item's tests>
vendor/bin/sail composer lint
vendor/bin/sail composer test:lint
vendor/bin/sail composer test:types
```

Iterate until all four are green.

## 6. Gate

If the item's line carries `<!-- gate -->`, also run the full gate and do not commit until it
passes:

```bash
vendor/bin/sail exec -e COMPOSER_PROCESS_TIMEOUT=0 laravel.test composer test
```

## 7. Commit

Tick the item in `.claude/backlog.md` (`[ ]` → `[x]`), stage everything, and commit with the
item's `Commit:` line **verbatim**. `resources/js/{actions,routes,wayfinder}` are gitignored and
rebuilt by Vite, so there is nothing of theirs to stage:

```bash
git add -A
git commit -m "<the Commit: line>"
```

Subject line only, at most 60 characters. No body, no `Refs:`, no scope parentheses, no
attribution of any kind.

## 8. Report

One line, nothing else:

```
<id> done — <n> tests, gate <ran|skipped>
```

---

## Hard rules

### Shipping

- **One item per invocation.** Finish it completely or block it; never start the next one.
- **Commit to `main`, locally.** One commit per item. **Never `git push`, never open a pull
  request, never change branch.** The user reviews the log and pushes when they want to.
- **Never weaken a test or a threshold.** 100% type coverage, PHPStan max and exactly 100% line
  coverage are permanent. Never delete or skip an existing test.
- **Three failed attempts** at getting a check green → mark the item `[!]` with the failure
  summarised in one line, commit only that backlog edit as `chore: block <id>`, and stop.

### What this effort may not change

- **The numbers do not move.** No item may change the result of any `app/Actions/Calculate*` or
  `app/Actions/Build*`. This is a shape and surface change on a working app. If a figure on
  screen changes, that is a bug in the item, not an improvement.
  `tests/Feature/GoldenDataset/*` is the tripwire — those tests pass untouched at every item.
- **No schema change, no migration, no new dependency** unless the item names it. The only
  pre-approved new primitive is shadcn `textarea` (it pulls no new Radix package). `pagination`,
  `tabs`, `command`, `popover`, `collapsible` and `skeleton` are already vendored and unused or
  barely used — reach for those before hand-rolling anything.
- **Money is integer cents.** No floats anywhere in money logic. Derived figures that can
  legitimately go negative (net worth, variance, net cash flow, closing balance) are signed
  `int` cents, not `Money`.
- **Calculation Actions take `today` as a parameter** and never call `now()`, so tests can pin
  the date.

### UI rules

- **Colour comes from tokens.** No raw Tailwind colour utility in `resources/js` — no
  `amber-500`, `emerald-700`, `red-600`. Use `--status-ok|warning|over`, `--series-plan|actual|
  forecast`, `destructive`, `muted-foreground`. Per PRD §5.2 colour is **never the only signal**:
  pair it with an icon, a label, or a line style.
- **Both widths, every item.** Anything visual works at 375px and 1280px, is keyboard operable,
  and has labelled inputs. Tables over four columns become card lists below 768px — the budget
  grid excepted, which switches to one month at a time.
- **Money and dates go through `usePreferences()`**, `lib/money.ts` and `lib/dates.ts`. Never
  a bare `toLocaleString` and never arithmetic on a formatted value. Figures carry `tabular-nums`.
- **Moved routes keep their name.** Change the URI and the page component path; leave the route
  name alone so the existing `route()` call sites and Wayfinder imports keep working. Add a
  redirect from the old URI in the same item, and regenerate Wayfinder.
- **Never hand-edit** `resources/js/actions` or `resources/js/routes` — they are gitignored
  generated output. Regenerate them, always with `--with-form`, or the `.form()` helpers vanish
  and the build breaks.
- Glossary tooltip copy is PRD §5.3 **verbatim**, on first appearance of a term per screen.

### Testing traps, learned the hard way

- **Coverage is scoped to `app/` and must be exactly 100%.** Never land a class in `app/` ahead
  of the code that exercises it, and test every `match` arm.
- **Coverage will not credit a multi-line ternary, `match` arm, or closure inside an array
  literal**, even when a test plainly exercises it — it reports 100% in isolation and short
  of it in the parallel run. Do not chase it with more tests: pull the expression into a
  small named method with explicit early returns, or replace a mapping closure with a
  `foreach`. The code reads better for it, and the branch is then counted.
- **Browser tests assert through the interface** — the app runs in a separate process, so
  re-reading a model returns a stale snapshot. End each interaction with a waiting assertion
  (a path change or visible text) before asserting anything else.
- **Browser test selectors go through `GuessLocator`**, which understands `@testid`, `#id`,
  `[name=…]` and visible text — but not a bare `[data-attr="…"]`, which silently falls through
  and can type into whatever is focused. Give anything a test needs to reach an `id` or a
  `data-testid`. When a browser test fails, **read the screenshot under
  `tests/Browser/Screenshots` before changing the selector** — it usually shows the real cause.
- **A route move breaks `assertPathIs`.** Update every browser assertion in the same item that
  moves the route, never later.
- **A Radix Popover inside the quick-add Dialog needs `modal`**, or it portals outside the
  dialog and the dialog's `pointer-events: none` leaves its options visible but unclickable.
- **A scripted string replacement can match in more than one place.** Anchor on text unique
  to the method you mean, or assert the match count — a props line inserted into two
  controller methods at once compiles fine and fails eight tests later.
- **Pest test helpers are global functions across the whole suite.** A bare `spend()` or
  `expenseCategory()` in a new test file collides with one in another and kills the run with
  `Cannot redeclare function`. Prefix helpers with the subject under test (`flowSpend`,
  `txCategory`, `summaryRecord`), or put genuinely shared ones in `tests/Pest.php`.
- **Browser flakiness is almost always contention.** If a browser test fails in the full run
  but passes alone — especially if a *different* one fails each run — lower
  `PEST_PROCESSES` (default 3) rather than touching the test. Never raise a timeout or weaken
  an assertion to chase it.
- **An interrupted browser run leaves Chromium behind.** If the gate suddenly takes minutes
  instead of under a minute, check `uptime` and
  `vendor/bin/sail exec laravel.test sh -c "ps aux | grep -c '[p]laywright'"`. Clear them with
  `vendor/bin/sail exec laravel.test sh -c "pkill -f playwright || true"` and wait for the load
  average to settle before trusting any result — a saturated machine fails tests that are fine.
- The full gate can exceed Composer's 300s process timeout; run it as
  `vendor/bin/sail exec -e COMPOSER_PROCESS_TIMEOUT=0 laravel.test composer test`.
