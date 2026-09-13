# Recorded project rules

Decisions taken during implementation that later sessions must respect. Each records what was
decided and why, so it is not "corrected" back.

## Mass assignment stays protected

The starter kit ships `Unguard => true` in `config/essentials.php`. This project overrides it to
`false`. Models therefore declare their fillable attributes explicitly, and columns that must
never come from request data — verification timestamps, the admin flag, remember tokens — are
written with `forceFill()` by the Action that owns them.

## Password rules are defined in FortifyServiceProvider, not Essentials

Essentials' `SetDefaultPassword` applies no rules outside production and adds `symbols()` inside
it. The requirement is at least twelve characters with mixed case and numbers in every
environment, and breach checking in production only. Essentials' version is disabled and
`Password::defaults()` is set explicitly.

## Assertions execute in development and tests

`zend.assertions = 1` is set in `docker/8.5/php.ini`. PHP's default of `-1` compiles `assert()`
out entirely, which hides contract violations and leaves those lines uncovered, breaking the
100% coverage gate. CI sets the same value. Production keeps PHP's default.

## Eloquent scopes are written as static query helpers

Rector forces `scope*` methods — and `#[Scope]`-attributed ones — to be `protected`, while the
strict architecture preset forbids protected methods on final classes. The two cannot both be
satisfied by a scope, so reusable queries are plain `public static` methods returning a Builder.

## Wayfinder must be regenerated with --with-form

`sail artisan wayfinder:generate` without `--with-form` strips every `.form()` helper and breaks
the build, because `vite.config.ts` generates them. Always pass the flag, or let Vite do it.

## Browser tests assert through the interface

The application under browser test runs in a separate process. Under MySQL's REPEATABLE READ the
test's own transaction cannot see rows that process commits, so re-reading a model returns a
stale snapshot. Assert what the user sees instead.

## Invitations carry the admin grant

`invitations.is_admin` is not in the original schema list. It exists because `app:invite --admin`
and the first production admin have no other way to grant the flag.

## Boost has no Pest 5 skill

Boost's catalogue stops at Pest 4 and has no `infer-conventions` skill at all. The gap is covered
by `.ai/guidelines/testing.blade.php`. Prefer official Pest 5 documentation over `search-docs`
results describing Pest 3.x or 4.x.

## Money is for stored amounts; derived figures that can go negative are signed cents

`Money` refuses negative values on purpose: a stored amount's direction comes from its
type, never its sign. But plenty of *derived* figures are legitimately negative — net
worth when debts exceed assets, variance when actual is under plan, net cash flow in a
losing month, a closing balance that goes below zero (which the forecast alerts on).

Those are returned as plain signed `int` cents, not `Money`. Do not clamp them at zero to
fit them into `Money`; that would silently report a negative net worth as nothing.

## Commit messages are a subject line only

Earlier guidance asked for Conventional Commits with a scope, a body explaining why, and the
requirement IDs touched — and the first milestones were written that way. The project owner
asked for the opposite: one line, at most 60 characters, a plain type prefix without scope
parentheses, no body, no IDs. `feat: quick add transactions`, not
`feat(transactions): quick add [TXQ-01..TXQ-10]`.

Requirement IDs have not gone anywhere; they stay in code PHPDoc, where they sit next to the
logic they justify rather than in a log nobody greps. The same restraint applies to PR titles
and descriptions.

## The remaining work is driven by a backlog and a loop

`.claude/backlog.md` holds the outstanding work in dependency order, and
`.claude/commands/next-item.md` implements exactly one item per invocation — read the PRD for
its requirement IDs, build, test, commit, tick. The last item of each phase carries
`<!-- gate -->`, which is where the full `sail composer test` runs; other items run targeted
Pest plus `test:lint` and `test:types`.

v1 shipped this way across seven milestones; the backlog now holds the UI modernisation pass,
whose items may not change any `Calculate*` or `Build*` result — the golden-dataset tests are
the tripwire.

All of it stays local, committing to `main`. The loop never pushes and never opens a pull
request.

## The test suite runs three workers, and browser tests get a minute

`pest --parallel` defaults to one worker per core. On a sixteen-core machine that means
sixteen simultaneous Chromium instances under Xdebug coverage, which thrashes the box: the
suite took 216 seconds and failed two browser tests, each of which passed on its own. The
failures moved between runs, which is what contention looks like rather than a bug.

`composer test:unit` therefore pins `--processes=${PEST_PROCESSES:-3}` and `tests/Pest.php`
raises the browser timeout to sixty seconds for the `Browser` suite. Same suite, same
assertions, 60 seconds and green. Neither is a quality threshold: coverage stays at exactly
100%, and a genuinely broken test still fails — it just no longer fails for being queued
behind five others. Override the worker count with `PEST_PROCESSES` where a different
machine wants a different number.

Expect to revisit this as browser tests grow. If flakiness returns, lower the worker count
before touching any assertion.

## Overlays do not nest on a phone

The category picker is a popover on a desktop and part of the form on a phone. A popover
inside the quick-add sheet renders its options where they can be seen but not clicked, and
on a 375px screen a second floating layer over a sheet is the wrong shape anyway.

The desktop popover needs `modal` for the same underlying reason: without it the popover
portals outside the dialog, where the dialog's own `pointer-events: none` guard applies.

Anything bulk or selection-related needs its own control in the mobile card list. The
desktop table header is hidden below 768px, so a control that lives only there — select-all
was the first — quietly becomes desktop-only.

## Percentage thresholds are compared by multiplying, not dividing

The budget warning threshold is a percentage, so the obvious test for "more than 10% over"
is `actual > planned * (1 + t)`. That divides money, which invites a rounding argument at
exactly the boundary the threshold exists to define — and `round()` is banned inside
`app/` by the NFR-02 architecture test anyway.

Both sides are multiplied by 100 instead: `actual * 100` against `planned * (100 + t)`.
Same answer, exactly, in integers. The tests pin each boundary to the penny — 77.000 is a
warning, 77.001 is over — so a future rewrite cannot quietly move the line.

Percentages themselves are never computed in PHP. The figures cross the wire as cents and
the interface divides, which is what "computed at full precision and rounded half-up to one
decimal only for display" asks for.

## Flashed status messages are toasts

Controllers say `->with('status', '…')`, which is Laravel's own convention. The starter
kit's toast hook only listened for a `toast` key, so across twenty controllers the message
was flashed and silently dropped — no success feedback appeared anywhere in the
application.

`useFlashToast` now honours both: a `toast` object where the tone matters, and a plain
`status` string, which is always a success. Prefer `->with('status', …)`; it reads better at
the call site and is what the rest of the framework expects.

## The emergency fund is part of the liquid balance

Adding an opening emergency fund to the golden fixture changed every balance figure in
the year — including ones a previous milestone had already pinned. That is correct, not a
regression: §7.2 defines the opening balance as Cash **plus** EmergencyFund, and Q-03
settled that the fund is tracked as a separate holding precisely so the forecast can tell
when it would be eaten into.

So the fund counts twice over, in two different senses: once in net worth as a holding,
and once in the liquid balance the cash flow runs from. `GoldenYear::OPENING_BALANCE` is
the cash alone; `OPENING_LIQUID` is what the balance series actually starts at. Anything
comparing against a balance wants the latter.
