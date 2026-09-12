---
description: Implement the next unchecked item in .claude/backlog.md, test it, and commit.
---

Implement **exactly one** backlog item, then stop. Do not start a second one, and do not ask
whether to continue — the loop invokes this command again.

## 1. Preflight

```bash
git rev-parse --abbrev-ref HEAD      # must be feat/m3-transactions
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

## 3. Read the spec before writing anything

Read the `docs/prd.md` sections for that item's `Refs:` — §7 for formulas, §8 for behaviour,
§9 for routes and page names, §10 for the architecture mapping, §12 for seed data. §7 is
**normative**: implement the formulas exactly as written. Also read the sibling files named in
the item's `Build:` line, so the new code matches what is already there.

If a formula is genuinely ambiguous and the item's `Notes:` do not settle it, mark the item
`[!]` with a one-line note, commit only that backlog edit as `chore: block <id>`, and stop.
Do not improvise financial logic.

## 4. Build

In this order, following sibling files for structure:

migration → model + factory → enum → `app/Data` DTO → Action → Policy → Form Request →
Controller → route → `vendor/bin/sail artisan wayfinder:generate --with-form` → React page and
components → `vendor/bin/sail bun run build` → tests.

- Generate files with `vendor/bin/sail artisan make:*  --no-interaction`.
- Add shadcn primitives with `printf 'n\n' | vendor/bin/sail bunx shadcn@latest add <component> --yes`
  — it prompts before overwriting an existing file, and an unanswered prompt hangs the loop.
  Answering `n` keeps the project's own version of anything it already has.
- **Run `vendor/bin/sail bun run build` after adding or renaming any page**, before running
  feature tests. Inertia resolves pages through the Vite manifest, so a page that has not been
  built makes every test touching it fail with `Unable to locate file in Vite manifest`.
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
vendor/bin/sail composer test
```

## 7. Commit

Tick the item in `.claude/backlog.md` (`[ ]` → `[x]`), stage everything including the generated
Wayfinder output, and commit with the item's `Commit:` line **verbatim**:

```bash
git add -A
git commit -m "<the Commit: line>"
```

Subject line only. No body, no `Refs:`, no scope parentheses, no attribution of any kind.

## 8. Report

One line, nothing else:

```
<id> done — <n> tests, gate <ran|skipped>
```

---

## Hard rules

- **One item per invocation.** Finish it completely or block it; never start the next one.
- **Never weaken a test or a threshold.** 100% type coverage, PHPStan max and exactly 100% line
  coverage are permanent. Never delete or skip an existing test.
- **Never hand-edit** `resources/js/actions` or `resources/js/routes` — regenerate them, always
  with `--with-form`, or the `.form()` helpers vanish and the build breaks.
- **Never `git push`, never open a PR, never change branch.** All work stays local on
  `feat/m3-transactions`.
- **No new dependencies** beyond those the item names. PRD §3.3 approves only `recharts` (via
  `shadcn add chart`) and `cmdk` (via `shadcn add command`); `popover` is allowed because the
  combobox requires it. Avoid shadcn `progress` — use a plain div bar.
- **Money is integer cents.** No floats anywhere in money logic. Derived figures that can
  legitimately go negative (net worth, variance, net cash flow, closing balance) are signed
  `int` cents, not `Money`.
- **Calculation Actions take `today` as a parameter** and never call `now()`, so tests can pin
  the date.
- **Browser tests assert through the interface** — the app runs in a separate process, so
  re-reading a model returns a stale snapshot. End each interaction with a waiting assertion
  (a path change or visible text) before asserting anything else.
- **Coverage is scoped to `app/` and must be exactly 100%.** Never land a class in `app/` ahead
  of the code that exercises it, and test every `match` arm.
- **Browser tests flake under parallel load.** A `Timeout 5000ms exceeded` in a browser test
  you did not touch is usually load, not a regression: re-run that test on its own before
  treating it as one. A real failure reproduces in isolation.
- **Three failed attempts** at getting a check green → mark the item `[!]` with the failure
  summarised in one line, commit only that backlog edit as `chore: block <id>`, and stop.
